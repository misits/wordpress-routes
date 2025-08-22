<?php

namespace WordPressRoutes\Routing\Traits;

defined("ABSPATH") or exit();

/**
 * Trait for handling AJAX routes
 *
 * @since 1.0.0
 */
trait HandlesAjaxRoutes
{
    /**
     * Register AJAX route
     */
    protected function registerAjaxRoute()
    {
        add_action('wp_ajax_' . $this->endpoint, [$this, 'handleAjaxRequest']);
        
        if (!empty($this->attributes['nopriv'])) {
            add_action('wp_ajax_nopriv_' . $this->endpoint, [$this, 'handleAjaxRequest']);
        }
    }

    /**
     * Handle AJAX request
     */
    public function handleAjaxRequest()
    {
        // Verify nonce if provided
        if (isset($_REQUEST['nonce'])) {
            if (!wp_verify_nonce($_REQUEST['nonce'], 'ajax_' . $this->endpoint)) {
                wp_die('Invalid nonce', 403);
            }
        }

        $request = $this->createAjaxRequest();

        // Process middleware
        foreach ($this->middleware as $middleware) {
            $result = $this->processAjaxMiddleware($middleware, $request);
            if ($result === false) {
                wp_die('Access Denied', 403);
            }
        }

        // Execute callback
        $response = $this->executeAjaxCallback($request);

        // Send response
        if (is_array($response) || is_object($response)) {
            wp_send_json_success($response);
        } elseif (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        } else {
            echo $response;
            wp_die();
        }
    }

    /**
     * Process AJAX middleware
     *
     * @param string|callable $middleware
     * @param array $request
     * @return bool
     */
    protected function processAjaxMiddleware($middleware, $request)
    {
        if ($middleware === 'auth' && !$request['is_authenticated']) {
            return false;
        }

        if (strpos($middleware, 'can:') === 0) {
            $capability = substr($middleware, 4);
            return current_user_can($capability);
        }

        if (is_callable($middleware)) {
            return $middleware($request) !== false;
        }

        return true;
    }

    /**
     * Execute AJAX callback
     *
     * @param array $request
     * @return mixed
     */
    protected function executeAjaxCallback($request)
    {
        return call_user_func($this->callback, $request);
    }

    /**
     * Create AJAX request
     *
     * @return array
     */
    protected function createAjaxRequest()
    {
        return [
            'get' => $_GET,
            'post' => $_POST,
            'request' => $_REQUEST,
            'user' => wp_get_current_user(),
            'is_authenticated' => is_user_logged_in(),
            'action' => $this->endpoint,
            'nonce' => wp_create_nonce('ajax_' . $this->endpoint)
        ];
    }

    /**
     * Allow non-privileged access (AJAX routes)
     *
     * @param bool $allow
     * @return self
     */
    public function nopriv($allow = true)
    {
        $this->attributes['nopriv'] = $allow;
        return $this;
    }
}