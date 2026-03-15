<?php

namespace App\Http\Controllers;

use App\Services\OpenApiSpecService;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function spec(OpenApiSpecService $service): JsonResponse
    {
        return response()->json($service->generate());
    }

    public function docs(): string
    {
        $specUrl = url('/api/openapi.json');

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>API Documentation</title>
            <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
        </head>
        <body>
            <div id="swagger-ui"></div>
            <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
            <script>
                SwaggerUIBundle({
                    url: '{$specUrl}',
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIBundle.SwaggerUIStandalonePreset
                    ],
                    layout: "BaseLayout"
                });
            </script>
        </body>
        </html>
        HTML;
    }
}
