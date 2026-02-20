<?php

namespace App\Enums;

enum AiModelInputType: string
{
    case TEXT = 'text';
    case TEXTAREA = 'textarea';
    case NUMBER = 'number';
    case INTEGER = 'integer';
    case BOOLEAN = 'boolean';
    case SELECT = 'select';
    case FILE = 'file'; // For images/files
    
    // Legacy support/Aliases
    case STRING = 'string';
    case FLOAT = 'float';
    case TOGGLE = 'toggle';
    case IMAGE = 'image';
    case IMAGES = 'images';
    case FILES = 'files';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
