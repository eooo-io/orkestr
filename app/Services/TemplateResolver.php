<?php

namespace App\Services;

class TemplateResolver
{
    /**
     * Replace {{variable_name}} placeholders with values from $variables array.
     */
    public function resolve(string $body, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $matches) use ($variables) {
            $key = $matches[1];

            return $variables[$key] ?? $matches[0];
        }, $body);
    }

    /**
     * Extract all variable names found in the body.
     *
     * @return string[]
     */
    public function extractVariables(string $body): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Return variable names that have no value provided.
     *
     * @return string[]
     */
    public function getMissing(string $body, array $variables): array
    {
        $found = $this->extractVariables($body);

        return array_values(array_filter($found, function (string $name) use ($variables) {
            return ! array_key_exists($name, $variables) || $variables[$name] === null;
        }));
    }
}
