<?php
/**
 * Verbose AI - Remote HTTP MCP Server Endpoint
 * Implements the Model Context Protocol (Streamable HTTP / JSON-RPC 2.0).
 * Accessible at /mcp or /api/mcp.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-api-key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../src/MCPService.php';

$mcpService = new MCPService();

// Handle GET for server discovery / ping
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'name' => 'verbose-ai',
        'version' => '1.0.0',
        'protocol' => 'mcp/2024-11-05',
        'status' => 'online',
        'endpoint' => '/api/mcp',
        'tools_count' => count($mcpService->getToolDefinitions()),
        'documentation' => 'Send JSON-RPC 2.0 POST requests to this endpoint.'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Read JSON-RPC payload from POST body
$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => ['code' => -32700, 'message' => 'Parse error: Empty request body']
    ]);
    exit;
}

$request = json_decode($rawBody, true);
if (!is_array($request)) {
    http_response_code(400);
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => null,
        'error' => ['code' => -32700, 'message' => 'Parse error: Invalid JSON']
    ]);
    exit;
}

// Execute through MCPService
$response = $mcpService->handleRequest($request);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
