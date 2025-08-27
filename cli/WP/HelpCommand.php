<?php

namespace WordPressRoutes\CLI\WP;

use \WP_CLI_Command;
use \WP_CLI;

/**
 * WordPress Routes Help commands for WP-CLI
 */
class HelpCommand extends \WP_CLI_Command
{
    /**
     * Show WordPress Routes CLI help
     *
     * ## EXAMPLES
     *
     *     wp routes:help
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke($args, $assoc_args)
    {
        \WP_CLI::line("");
        \WP_CLI::line("NAME");
        \WP_CLI::line("WordPress Routes");

        \WP_CLI::line("");
        \WP_CLI::line("DESCRIPTION");
        \WP_CLI::line(
            "WordPress Routes is a command-line tool for managing routes, controllers, and middleware.",
        );
        \WP_CLI::line(
            "It provides a simple and intuitive interface for developers to manage their application routing.",
        );

        \WP_CLI::line("");
        \WP_CLI::line("USAGE");
        \WP_CLI::line("  wp routes:<command> [<args>]");

        \WP_CLI::line("");

        \WP_CLI::line("CONTROLLER COMMANDS:");
        \WP_CLI::line(
            "  wp routes:make-controller <name>     Create a new controller",
        );
        \WP_CLI::line(
            "    --path=<path>                        Path to controllers directory",
        );
        \WP_CLI::line(
            "    --api                                Generate API controller",
        );
        \WP_CLI::line(
            "    --resource                           Generate resource controller",
        );
        \WP_CLI::line(
            "    --namespace=<namespace>              Specify namespace for controller",
        );
        \WP_CLI::line(
            "  wp routes:controller-list            List all controllers",
        );
        \WP_CLI::line(
            "    --format=<format>                    Render output format (table, csv, json, yaml)",
        );
        \WP_CLI::line("");

        \WP_CLI::line("MIDDLEWARE COMMANDS:");
        \WP_CLI::line(
            "  wp routes:make-middleware <name>     Create a new middleware",
        );
        \WP_CLI::line(
            "    --path=<path>                        Path to middleware directory",
        );
        \WP_CLI::line(
            "    --namespace=<namespace>              Specify namespace for middleware",
        );
        \WP_CLI::line(
            "  wp routes:middleware-list            List all middleware",
        );
        \WP_CLI::line(
            "    --format=<format>                    Render output format (table, csv, json, yaml)",
        );
        \WP_CLI::line("");

        \WP_CLI::line("ROUTE COMMANDS:");
        \WP_CLI::line(
            "  wp routes:list                 List all registered routes",
        );
        \WP_CLI::line("");

        \WP_CLI::line("EXAMPLES:");
        \WP_CLI::line("  wp routes:make-controller ProductController --api");
        \WP_CLI::line("  wp routes:make-controller UserController --resource");
        \WP_CLI::line("  wp routes:make-middleware AuthMiddleware");
        \WP_CLI::line("");

        \WP_CLI::line("MODE CONFIGURATION:");
        \WP_CLI::line("Set WPROUTES_MODE in your functions.php or plugin:");
        \WP_CLI::line(
            "  define('WPROUTES_MODE', 'theme');   // For theme development",
        );
        \WP_CLI::line(
            "  define('WPROUTES_MODE', 'plugin');  // For plugin development",
        );
        \WP_CLI::line("");

        \WP_CLI::line("VERSION:");
        if (function_exists("wproutes_version")) {
            \WP_CLI::line("  WordPress Routes: " . wproutes_version());
        }
        \WP_CLI::line("");
    }
}
