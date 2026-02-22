import React, { useState } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { AiModelInputType } from '@/types/AiModelInputType';
import DragDropUploader from '@/Components/Generation/DragDropUploader';

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
        const type = (field.type || AiModelInputType.TEXT).trim().toLowerCase();
        
        // Ensure value is never null/undefined for controlled inputs
        let value = data[key];
        if (value === null || value === undefined) {
             if (type === AiModelInputType.TOGGLE || type === AiModelInputType.BOOLEAN) {
                 value = false;
             } else {
                 value = '';
             }
        }

        const error = errors[key] || errors[`input_data.${key}`]; 

        return (
            <div key={key} className="space-y-1">
                <div className="flex items-center justify-between">
                    <InputLabel htmlFor={key} value={label || key} />
                    {required && <span className="text-[10px] text-red-500 font-bold uppercase tracking-wider">Required</span>}
                </div>
                
                {description && (
                    <p className="text-[11px] text-gray-400 dark:text-gray-500 mb-2 italic leading-tight">{description}</p>
                )}

                {/* Text Field */}
                {(type === AiModelInputType.TEXT || type === AiModelInputType.STRING) && (
                    <TextInput
                        id={key}
                        type="text"
                        className="mt-1 block w-full border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        placeholder={placeholder}
                        required={required}
                    />
                )}

                {/* Textarea */}
                {type === AiModelInputType.TEXTAREA && (
                    <textarea
                        id={key}
                        className="mt-1 block w-full border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm min-h-[100px] transition-all"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        placeholder={placeholder}
                        required={required}
                    />
                )}

                {/* Number */}
                {(type === AiModelInputType.NUMBER || type === AiModelInputType.INTEGER || type === AiModelInputType.FLOAT) && (
                    <TextInput
                        id={key}
                        type="number"
                        step="any"
                        className="mt-1 block w-full border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
                        value={value}
                        onChange={(e) => handleInputChange(key, e.target.value)}
                        min={min}
                        max={max}
                        required={required}
                    />
                )}

                {/* Select */}
                {type === AiModelInputType.SELECT && (
                    <select
                        id={key}
                        className="mt-1 block w-full border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm"
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
                {(type === AiModelInputType.TOGGLE || type === AiModelInputType.BOOLEAN) && (
                    <div className="flex items-center mt-2">
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input 
                                type="checkbox" 
                                className="sr-only peer"
                                checked={!!value}
                                onChange={(e) => handleInputChange(key, e.target.checked)}
                            />
                            <div className="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white dark:after:bg-gray-300 after:border-gray-300 dark:after:border-gray-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 dark:peer-checked:bg-indigo-500"></div>
                            <span className="ml-3 text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Enabled</span>
                        </label>
                    </div>
                )}

                {/* Image / File (Single) */}
                {(type === AiModelInputType.IMAGE || type === AiModelInputType.FILE) && (
                    <DragDropUploader 
                        fieldKey={key}
                        value={value}
                        onChange={(val: any) => handleInputChange(key, val)}
                        isMultiple={false}
                        accept={type === AiModelInputType.IMAGE ? "image/*" : "*/*"}
                    />
                )}

                {/* Images (Multiple) */}
                {type === AiModelInputType.IMAGES && (
                    <DragDropUploader 
                        fieldKey={key}
                        value={value}
                        onChange={(val: any) => handleInputChange(key, val)}
                        isMultiple={true}
                        accept="image/*"
                    />
                )}

                {!Object.values(AiModelInputType).includes(type as any) && (
                    <div className="p-2 border border-dashed border-red-300 dark:border-red-800/50 rounded bg-red-50 dark:bg-red-900/20 text-[10px] text-red-600 dark:text-red-400">
                        Unknown field type: <strong>{type}</strong>. 
                    </div>
                )}

                <InputError message={error} className="mt-2" />
            </div>
        );
    };

    if (!schema || !Array.isArray(schema)) {
        return <div className="p-4 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 rounded-lg">Invalid schema format.</div>;
    }

    return (
        <div className="space-y-4">
            {schema.map((field: any) => {
                return (
                    <div key={field.key}>
                        {renderField(field)}
                    </div>
                );
            })}
        </div>
    );
}
