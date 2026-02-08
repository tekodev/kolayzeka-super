import React from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

interface DynamicFormProps {
    schema: any[];
    data: any;
    errors: Record<string, string>;
    setData: (key: string, value: any) => void;
}

export default function DynamicForm({ schema, data, errors, setData }: DynamicFormProps) {
    const handleInputChange = (key: string, value: any) => {
        setData(key, value);
    };

    const renderField = (field: any) => {
        const { key, label, description, required, options, min, max, placeholder } = field;
        const type = (field.type || 'text').trim().toLowerCase();
        
        // Ensure value is never null/undefined for controlled inputs
        let value = data[key];
        if (value === null || value === undefined) {
            value = (type === 'toggle' || type === 'boolean') ? false : '';
        }

        const error = errors[key] || errors[`input_data.${key}`]; 

        return (
            <div key={key} className="space-y-1">
                <div className="flex items-center justify-between">
                    <InputLabel htmlFor={key} value={label || key} />
                    {required && <span className="text-[10px] text-red-500 font-bold uppercase tracking-wider">Required</span>}
                </div>
                
                {description && (
                    <p className="text-[11px] text-gray-400 mb-2 italic leading-tight">{description}</p>
                )}

                {/* Text Field */}
                {(type === 'text' || type === 'string') && (
                    <TextInput
                        id={key}
                        type="text"
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        placeholder={placeholder}
                        required={required}
                    />
                )}

                {/* Textarea */}
                {type === 'textarea' && (
                    <textarea
                        id={key}
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm min-h-[100px] transition-all bg-white"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        placeholder={placeholder}
                        required={required}
                    />
                )}

                {/* Number */}
                {(type === 'number' || type === 'integer' || type === 'float') && (
                    <TextInput
                        id={key}
                        type="number"
                        step="any"
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        min={min}
                        max={max}
                        required={required}
                    />
                )}

                {/* Select */}
                {type === 'select' && (
                    <select
                        id={key}
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm bg-white"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        required={required}
                    >
                        <option value="">Select option...</option>
                        {options?.map((opt: any) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label || opt.value}
                            </option>
                        ))}
                    </select>
                )}

                {/* Toggle / Boolean */}
                {(type === 'toggle' || type === 'boolean') && (
                    <div className="flex items-center mt-2">
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                className="sr-only peer"
                                checked={!!value}
                                onChange={(e) => handleInputChange(key, e.target.checked)}
                            />
                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span className="ml-3 text-sm font-medium text-gray-500 uppercase tracking-wide">Enabled</span>
                        </label>
                    </div>
                )}

                {/* Image / File */}
                {type === 'image' && (
                    <div className="mt-1">
                        <div className="flex items-center justify-center w-full">
                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg className="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                        <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                    </svg>
                                    <p className="mb-2 text-sm text-gray-500 text-center px-4"><span className="font-semibold">Click to upload</span> or drag and drop</p>
                                </div>
                                <input 
                                    type="file" 
                                    className="hidden" 
                                    onChange={(e) => handleInputChange(key, e.target.files?.[0])}
                                    accept="image/*"
                                />
                            </label>
                        </div>
                        {value && typeof value !== 'string' && (
                             <div className="mt-2 p-2 bg-indigo-50 rounded-lg flex items-center justify-between text-xs text-indigo-700 font-medium">
                                <span>Selected: {(value as File).name}</span>
                                <button type="button" onClick={() => handleInputChange(key, null)} className="text-red-500 hover:text-red-700">Remove</button>
                             </div>
                        )}
                    </div>
                )}

                {!['text', 'string', 'textarea', 'number', 'integer', 'float', 'select', 'toggle', 'boolean', 'image'].includes(type) && (
                    <div className="p-2 border border-dashed border-red-300 rounded bg-red-50 text-[10px] text-red-600">
                        Unknown field type: <strong>{type}</strong>. 
                    </div>
                )}

                <InputError message={error} className="mt-2" />
            </div>
        );
    };

    if (!schema || !Array.isArray(schema)) {
        return <div className="p-4 bg-yellow-50 text-yellow-700 rounded-lg">Invalid schema format.</div>;
    }

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
            {schema.map((field: any) => {
                const type = (field.type || 'text').toLowerCase();
                const isFullWidth = type === 'textarea' || type === 'image' || field.fullWidth;
                return (
                    <div key={field.key} className={isFullWidth ? 'md:col-span-2' : ''}>
                        {renderField(field)}
                    </div>
                );
            })}
        </div>
    );
}
