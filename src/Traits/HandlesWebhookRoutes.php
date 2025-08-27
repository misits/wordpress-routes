<?php

namespace WordPressRoutes\Routing\Traits;

defined("ABSPATH") or exit();

/**
 * Trait for handling Webhook routes
 *
 * @since 1.0.0
 */
trait HandlesWebhookRoutes
{
    /**
     * Register webhook route as REST API endpoint
     */
    protected function registerWebhookRoute()
    {
        add_action('rest_api_init', function() {
            register_rest_route('webhooks/v1', $this->endpoint, [
                'methods' => 'POST',
                'callback' => [$this, 'handleWebhookRequest'],
                'permission_callback' => '__return_true', // Public by default
                'args' => []
            ]);
        });
    }

    /**
     * Handle webhook request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|WP_Error
     */
    public function handleWebhookRequest($request)
    {
        // Create webhook request object
        $webhookRequest = $this->createWebhookRequest($request);

        // Process middleware
        foreach ($this->middleware as $middleware) {
            $result = $this->processWebhookMiddleware($middleware, $webhookRequest);
            if ($result === false) {
                return new \WP_Error('access_denied', 'Access Denied', ['status' => 403]);
            }
        }

        // Execute callback
        try {
            $response = $this->executeWebhookCallback($webhookRequest);
            
            // Handle different response types
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (is_array($response) || is_object($response)) {
                return new \WP_REST_Response($response, 200);
            }
            
            return new \WP_REST_Response(['success' => true, 'data' => $response], 200);
            
        } catch (\Exception $e) {
            return new \WP_Error('webhook_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Process webhook middleware
     *
     * @param string|callable $middleware
     * @param array $request
     * @return bool
     */
    protected function processWebhookMiddleware($middleware, $request)
    {
        // Signature verification middleware
        if (strpos($middleware, 'signature:') === 0) {
            $secret = substr($middleware, 10);
            return $this->verifySignature($request, $secret);
        }

        // Bearer token middleware
        if (strpos($middleware, 'bearer:') === 0) {
            $token = substr($middleware, 7);
            return $this->verifyBearerToken($request, $token);
        }

        // IP whitelist middleware
        if (strpos($middleware, 'ip:') === 0) {
            $allowedIps = explode(',', substr($middleware, 3));
            return $this->verifyIpAddress($request, $allowedIps);
        }

        // Custom middleware
        if (is_callable($middleware)) {
            return $middleware($request) !== false;
        }

        return true;
    }

    /**
     * Execute webhook callback
     *
     * @param array $request
     * @return mixed
     */
    protected function executeWebhookCallback($request)
    {
        return call_user_func($this->callback, $request);
    }

    /**
     * Create webhook request object
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    protected function createWebhookRequest($request)
    {
        $body = $request->get_body();
        $headers = $request->get_headers();
        
        // Try to parse JSON body
        $parsedBody = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $parsedBody = null;
        }

        return [
            'endpoint' => $this->endpoint,
            'method' => $request->get_method(),
            'headers' => $headers,
            'body' => $body,
            'json' => $parsedBody,
            'params' => $request->get_params(),
            'query' => $request->get_query_params(),
            'client_ip' => $this->getClientIp(),
            'user_agent' => $headers['user_agent'][0] ?? '',
            'content_type' => $headers['content_type'][0] ?? '',
        ];
    }

    /**
     * Verify webhook signature (GitHub style)
     *
     * @param array $request
     * @param string $secret
     * @return bool
     */
    protected function verifySignature($request, $secret)
    {
        $signature = $request['headers']['x_hub_signature_256'][0] ?? 
                    $request['headers']['x-hub-signature-256'][0] ?? null;

        if (!$signature) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $request['body'], $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify bearer token
     *
     * @param array $request
     * @param string $expectedToken
     * @return bool
     */
    protected function verifyBearerToken($request, $expectedToken)
    {
        $authorization = $request['headers']['authorization'][0] ?? '';
        
        if (strpos($authorization, 'Bearer ') !== 0) {
            return false;
        }
        
        $token = substr($authorization, 7);
        
        return hash_equals($expectedToken, $token);
    }

    /**
     * Verify IP address whitelist
     *
     * @param array $request
     * @param array $allowedIps
     * @return bool
     */
    protected function verifyIpAddress($request, $allowedIps)
    {
        $clientIp = $request['client_ip'];
        
        foreach ($allowedIps as $allowedIp) {
            $allowedIp = trim($allowedIp);
            if ($clientIp === $allowedIp) {
                return true;
            }
            
            // Check CIDR notation
            if (strpos($allowedIp, '/') !== false) {
                if ($this->ipInCidr($clientIp, $allowedIp)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    protected function getClientIp()
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster balancer
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if IP is in CIDR range
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    protected function ipInCidr($ip, $cidr)
    {
        list($subnet, $bits) = explode('/', $cidr);
        
        if ($bits === null) {
            $bits = 32;
        }
        
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        
        return ($ip & $mask) == $subnet;
    }

    /**
     * Add signature verification middleware
     *
     * @param string $secret
     * @return self
     */
    public function signature($secret)
    {
        $this->middleware[] = 'signature:' . $secret;
        return $this;
    }

    /**
     * Add bearer token authentication
     *
     * @param string $token
     * @return self
     */
    public function bearer($token)
    {
        $this->middleware[] = 'bearer:' . $token;
        return $this;
    }

    /**
     * Add IP whitelist
     *
     * @param string|array $ips
     * @return self
     */
    public function allowIps($ips)
    {
        if (is_array($ips)) {
            $ips = implode(',', $ips);
        }
        $this->middleware[] = 'ip:' . $ips;
        return $this;
    }
}