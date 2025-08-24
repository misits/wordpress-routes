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
        \WP_CLI::add_command('borps routes:help', HelpCommand::class);

        // Register controller commands
        \WP_CLI::add_command('borps routes:make-controller', ControllerCommand::class);
        \WP_CLI::add_command('borps routes:controller-list', [ControllerCommand::class, 'listControllers']);

        // Register middleware commands
        \WP_CLI::add_command('borps routes:make-middleware', MiddlewareCommand::class);
        \WP_CLI::add_command('borps routes:middleware-list', [MiddlewareCommand::class, 'listMiddleware']);
        
        // Register route commands
        \WP_CLI::add_command('borps routes:list', [ControllerCommand::class, 'listRoutes']);
        \WP_CLI::add_command('borps routes:flush', [ControllerCommand::class, 'flushRoutes']);
        \WP_CLI::add_command('borps routes:debug', [ControllerCommand::class, 'debugRoutes']);
    }
}