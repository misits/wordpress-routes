<?php

namespace WordPressRoutes\CLI\WP;

/**
 * WP-CLI Command Registrar for WordPress Routes
 */
class CommandRegistrar
{
    /**
     * Register all WP-CLI commands
     */
    public static function register()
    {
        if (!class_exists('WP_CLI')) {
            return;
        }

        // Load command classes
        require_once __DIR__ . '/ControllerCommand.php';
        require_once __DIR__ . '/MiddlewareCommand.php';
        require_once __DIR__ . '/HelpCommand.php';

        // Register help command
        \WP_CLI::add_command('wproutes help', HelpCommand::class);

        // Register controller commands
        \WP_CLI::add_command('wproutes make:controller', ControllerCommand::class);
        \WP_CLI::add_command('wproutes controller:list', [ControllerCommand::class, 'listControllers']);

        // Register middleware commands
        \WP_CLI::add_command('wproutes make:middleware', MiddlewareCommand::class);
        \WP_CLI::add_command('wproutes middleware:list', [MiddlewareCommand::class, 'listMiddleware']);
        
        // Register route commands
        \WP_CLI::add_command('wproutes route:list', [ControllerCommand::class, 'listRoutes']);
    }
}