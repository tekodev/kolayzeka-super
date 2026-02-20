export enum AiModelInputType {
    TEXT = 'text',
    TEXTAREA = 'textarea',
    NUMBER = 'number',
    INTEGER = 'integer',
    BOOLEAN = 'boolean',
    SELECT = 'select',
    FILE = 'file',
    IMAGE = 'image', // Alias for file/image upload
    IMAGES = 'images', // Multiple image upload
    
    // Legacy / Aliases
    STRING = 'string',
    FLOAT = 'float',
    TOGGLE = 'toggle',
}

export const INPUT_TYPES = Object.values(AiModelInputType);
