<?php

namespace App\Http\Controllers;

use App\Services\SdkGeneratorService;
use Illuminate\Http\Response;

class SdkController extends Controller
{
    public function __construct(
        protected SdkGeneratorService $generator,
    ) {}

    public function typescript(): Response
    {
        $content = $this->generator->generateTypeScript();

        return response($content, 200, [
            'Content-Type' => 'text/typescript',
            'Content-Disposition' => 'attachment; filename="orkestr-client.ts"',
        ]);
    }

    public function php(): Response
    {
        $content = $this->generator->generatePhp();

        return response($content, 200, [
            'Content-Type' => 'text/x-php',
            'Content-Disposition' => 'attachment; filename="OrkestrClient.php"',
        ]);
    }
}
