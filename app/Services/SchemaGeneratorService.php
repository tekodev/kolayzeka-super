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
            
            // Ensure array/object defaults are stringified for Text/Textarea inputs
            if (is_array($default) || is_object($default)) {
                $default = json_encode($default, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

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

            // Handle Enum/Select (Direct or AnyOf)
            $enumOptions = null;
            if (isset($prop['enum'])) {
                $enumOptions = $prop['enum'];
            } elseif (isset($prop['anyOf'])) {
                foreach ($prop['anyOf'] as $anyOfItem) {
                    if (isset($anyOfItem['enum'])) {
                        $enumOptions = $anyOfItem['enum'];
                        break;
                    }
                }
            }

            if ($enumOptions !== null) {
                $schemaItem['type'] = 'select';
                $schemaItem['options'] = array_map(function($val) {
                    return ['label' => ucfirst(str_replace('_', ' ', $val)), 'value' => $val];
                }, $enumOptions);
            }
            
            // Handle Number ranges
            if ($type === 'number') {
                if (isset($prop['minimum'])) $schemaItem['min'] = $prop['minimum'];
                if (isset($prop['maximum'])) $schemaItem['max'] = $prop['maximum'];
            }

            // Fix for 'image_urls' type (array of images)
            if (isset($prop['type']) && $prop['type'] === 'array' && str_contains(strtolower($key), 'image')) {
                $schemaItem['type'] = 'images';
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

        // Logic to suggest a Response Path based on output properties
        $suggestedPath = null;
        if (isset($outputSchema['properties'])) {
            $outProps = $outputSchema['properties'];
            $outKeys = array_keys($outProps);

            // Priority keywords for response paths
            $priorityKeywords = ['verdict', 'url', 'image', 'text', 'content', 'result'];
            
            foreach ($priorityKeywords as $keyword) {
                foreach ($outKeys as $oKey) {
                    if (str_contains(strtolower($oKey), $keyword)) {
                        $suggestedPath = $oKey;
                        break 2;
                    }
                }
            }

            // Fallback for nested images (Replicate/Fal style)
            if (!$suggestedPath && isset($outProps['images'])) {
                $suggestedPath = '';
            }
            
            // Final fallback: just take the first property if it's simple
            if (!$suggestedPath && !empty($outKeys)) {
                $suggestedPath = $outKeys[0];
            }
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
            'suggested_response_path' => $suggestedPath,
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
            // Check if plural "images" or "urls", return `images` or `image`
            return str_ends_with(strtolower($key), 'images') || str_ends_with(strtolower($key), 'urls') ? 'images' : 'image';
        }
        if (str_contains(strtolower($key), 'prompt') && ($providerType === 'string' || $providerType === 'textarea')) {
            return 'textarea';
        }
        return 'text';
    }
}
