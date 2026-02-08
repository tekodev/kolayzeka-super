<?php

namespace App\Services;

class SchemaGeneratorService
{
    /**
     * Parses raw provider JSON (e.g. Fal.ai OpenAPI/JSON Schema) and returns 
     * our standardized Client Schema and Field Mapping.
     */
    public function generateFromProviderJson(string|array $jsonInput): array
    {
        $data = is_array($jsonInput) ? $jsonInput : json_decode($jsonInput, true);
        
        \Log::info('SchemaGenerator input keys:', ['keys' => is_array($data) ? array_keys($data) : 'not an array']);
        
        if (!$data) {
            return [
                'input_schema' => [],
                'field_mapping' => [],
            ];
        }

        // Logic to extract Properties from various OpenAPI formats
        $properties = [];
        $inputSchemaName = 'Input';
        $outputSchemaName = 'Output';
        
        if (isset($data['components']['schemas'])) {
            $schemaKeys = array_keys($data['components']['schemas']);
            \Log::info('Available schemas in components:', ['schemas' => $schemaKeys]);
            
            // If generic 'Input' doesn't exist, look for something ending with 'Input'
            if (!isset($data['components']['schemas']['Input'])) {
                foreach ($schemaKeys as $sKey) {
                    if (str_ends_with($sKey, 'Input')) {
                        $inputSchemaName = $sKey;
                        break;
                    }
                }
            }

            // Similarly for Output
            if (!isset($data['components']['schemas']['Output'])) {
                foreach ($schemaKeys as $sKey) {
                    if (str_ends_with($sKey, 'Output')) {
                        $outputSchemaName = $sKey;
                        break;
                    }
                }
            }
        }

        // Pattern 1: Replicate / Fal often format as Open API components
        if (isset($data['components']['schemas'][$inputSchemaName]['properties'])) {
             $properties = $data['components']['schemas'][$inputSchemaName]['properties'];
        } 
        // Pattern 2: Fal filtered response sometimes has input_schema direct
        elseif (isset($data['input_schema']['properties'])) {
             $properties = $data['input_schema']['properties'];
        }
        // Pattern 3: Direct properties (legacy or simple)
        elseif (isset($data['properties'])) {
             $properties = $data['properties'];
        }
        // Pattern 4: Fal "openapi" key
        elseif (isset($data['openapi']['components']['schemas'][$inputSchemaName]['properties'])) {
            $properties = $data['openapi']['components']['schemas'][$inputSchemaName]['properties'];
        }

        // Extract required fields list if available
        $requiredFields = [];
        if (isset($data['components']['schemas'][$inputSchemaName]['required'])) {
            $requiredFields = $data['components']['schemas'][$inputSchemaName]['required'];
        } elseif (isset($data['input_schema']['required'])) {
            $requiredFields = $data['input_schema']['required'];
        } elseif (isset($data['required'])) {
            $requiredFields = $data['required'];
        } elseif (isset($data['openapi']['components']['schemas'][$inputSchemaName]['required'])) {
            $requiredFields = $data['openapi']['components']['schemas'][$inputSchemaName]['required'];
        }

        $clientSchema = [];
        $fieldMapping = [];

        foreach ($properties as $key => $prop) {
            $type = $this->mapType($prop['type'] ?? 'string', $key);
            $label = $prop['title'] ?? ucfirst(str_replace('_', ' ', $key));
            $default = $prop['default'] ?? null;
            $description = $prop['description'] ?? null;
            
            $schemaItem = [
                'key' => $key,
                'type' => $type,
                'label' => $label,
                'required' => in_array($key, $requiredFields), // Check if field is in required list
                'default' => $default,
            ];

            // Add description if available
            if ($description) {
                $schemaItem['description'] = $description;
            }

            // Handle Enum/Select
            if (isset($prop['enum'])) {
                $schemaItem['type'] = 'select';
                $schemaItem['options'] = array_map(function($val) {
                    return ['label' => ucfirst($val), 'value' => $val];
                }, $prop['enum']);
            }
            
            // Handle x-order or sorting if available (optional enhancement)

            // Handle Number ranges
            if ($type === 'number') {
                if (isset($prop['minimum'])) $schemaItem['min'] = $prop['minimum'];
                if (isset($prop['maximum'])) $schemaItem['max'] = $prop['maximum'];
            }

            $clientSchema[] = $schemaItem;
            
            // Default mapping: key to key
            $fieldMapping[$key] = $key;
        }

        // Logic to extract Output Schema
        $outputSchema = [];
        
        // Try to get full Output schema with properties
        if (isset($data['components']['schemas'][$outputSchemaName])) {
            $outputSchema = $data['components']['schemas'][$outputSchemaName];
        } elseif (isset($data['output_schema'])) {
            $outputSchema = $data['output_schema'];
        } elseif (isset($data['openapi']['components']['schemas'][$outputSchemaName])) {
            $outputSchema = $data['openapi']['components']['schemas'][$outputSchemaName];
        }
        
        // If output schema is simple (just a string/uri), enhance it with description
        if (isset($outputSchema['type']) && !isset($outputSchema['properties'])) {
            $outputSchema['description'] = 'API response - typically returns a ' . 
                ($outputSchema['format'] ?? $outputSchema['type']) . 
                ' containing the generated result';
        }

        // Generate example request body for client API
        $exampleInputData = [];
        $hasFileUploads = false;
        
        foreach ($clientSchema as $field) {
            $exampleValue = $field['default'] ?? $this->getExampleValue($field);
            $exampleInputData[$field['key']] = $exampleValue;
            
            // Check if any field requires file upload
            if ($field['type'] === 'image' || $field['type'] === 'file' || $field['type'] === 'video') {
                $hasFileUploads = true;
                $exampleInputData[$field['key']] = '(file upload)';
            }
        }

        // Determine content type based on whether files are needed
        $contentType = $hasFileUploads ? 'multipart/form-data' : 'application/json';
        
        // Build comprehensive API contract for client
        $apiContract = [
            'client_request' => [
                'method' => 'POST',
                'endpoint' => '/api/models/{slug}/generate',
                'content_type' => $contentType,
                'notes' => $hasFileUploads 
                    ? 'Use FormData for file uploads. Files will be stored and URLs sent to provider.'
                    : 'Send JSON body directly.',
                'body' => $exampleInputData, // Direct field structure, no wrapper
            ],
            'client_response' => [
                'success' => true,
                'generation' => [
                    'id' => 1,
                    'status' => 'completed',
                    'result_data' => [
                        'output' => 'Generated result (format depends on provider)',
                    ],
                    'cost' => 0.05,
                    'created_at' => '2026-02-05T20:00:00Z',
                ],
            ],
            'provider_output_schema' => $outputSchema,
        ];

        return [
            'input_schema' => $clientSchema, // Array format
            'output_schema' => $apiContract, // Full API contract
            'field_mapping' => $fieldMapping,
        ];
    }

    protected function getExampleValue(array $field): mixed
    {
        return match($field['type']) {
            'textarea', 'text' => 'example ' . strtolower($field['label']),
            'number' => $field['min'] ?? 1,
            'toggle' => false,
            'select' => $field['options'][0]['value'] ?? 'option1',
            'image', 'file', 'video' => '(file upload)',
            default => 'example value',
        };
    }

    protected function mapType(string $providerType, string $key): string
    {
        if ($providerType === 'integer' || $providerType === 'number') {
            return 'number';
        }
        if ($providerType === 'boolean') {
            return 'toggle';
        }
        if (str_contains(strtolower($key), 'image')) {
            return 'image';
        }
        if (str_contains(strtolower($key), 'prompt') && $providerType === 'string') {
            return 'textarea';
        }
        return 'text';
    }
}
