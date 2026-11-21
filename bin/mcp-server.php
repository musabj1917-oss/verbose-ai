#!/usr/bin/env php
<?php
/**
 * Verbose AI - Command-Line Stdio MCP Server
 * Runs as a standard Model Context Protocol process communicating via JSON-RPC 2.0 over STDIN/STDOUT.
 * Usage: php bin/mcp-server.php
 */

require_once __DIR__ . '/../src/MCPService.php';

$mcpService = new MCPService();

// Turn off output buffering for instant streaming
while (ob_get_level() > 0) {
    ob_end_flush();
}

// In stdio transport, log warnings/errors to STDERR only so STDOUT remains pure JSON-RPC
ini_set('display_errors', '0');
error_reporting(E_ALL);

$stdin = fopen('php://stdin', 'r');
if (!$stdin) {
    fwrite(STDERR, "Failed to open STDIN\n");
    exit(1);
}

while (!feof($stdin)) {
    $line = fgets($stdin);
    if ($line === false) {
        break;
    }
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $request = json_decode($line, true);
    if (!is_array($request)) {
        $error = [
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => -32700, 'message' => 'Parse error: Invalid JSON']
        ];
        echo json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        continue;
    }

    $response = $mcpService->handleRequest($request);
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}
fclose($stdin);
