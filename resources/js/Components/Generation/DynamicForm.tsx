import React from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { AiModelInputType } from '@/types/AiModelInputType';

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
                    <p className="text-[11px] text-gray-400 mb-2 italic leading-tight">{description}</p>
                )}

                {/* Text Field */}
                {(type === AiModelInputType.TEXT || type === AiModelInputType.STRING) && (
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
                {type === AiModelInputType.TEXTAREA && (
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
                {(type === AiModelInputType.NUMBER || type === AiModelInputType.INTEGER || type === AiModelInputType.FLOAT) && (
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
                {type === AiModelInputType.SELECT && (
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
                {(type === AiModelInputType.TOGGLE || type === AiModelInputType.BOOLEAN) && (
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

                {/* Image / File (Single) */}
                {(type === AiModelInputType.IMAGE || type === AiModelInputType.FILE) && (
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
                                    accept={type === AiModelInputType.IMAGE ? "image/*" : "*/*"} 
                                />
                            </label>
                        </div>
                        {value && !Array.isArray(value) && (
                             <div className="mt-2 p-2 bg-indigo-50 rounded-lg flex items-center justify-between text-xs text-indigo-700 font-medium">
                                <span className="truncate max-w-[80%]">
                                    {typeof value === 'string' 
                                        ? (value.startsWith('http') ? value.split('/').pop() : value) 
                                        : (value as File).name}
                                </span>
                                <button type="button" onClick={() => handleInputChange(key, null)} className="text-red-500 hover:text-red-700 ml-2 shrink-0">Remove</button>
                             </div>
                        )}
                    </div>
                )}

                {/* Images (Multiple) */}
                {type === AiModelInputType.IMAGES && (
                    <div className="mt-1 space-y-2">
                        <div className="flex items-center justify-center w-full">
                            <label className="flex flex-col items-center justify-center w-full h-24 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                <div className="flex flex-col items-center justify-center pt-4 pb-4">
                                    <svg className="w-6 h-6 mb-2 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                        <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                    </svg>
                                    <p className="text-xs text-gray-500"><span className="font-semibold">Click to upload multiple images</span></p>
                                </div>
                                <input 
                                    type="file" 
                                    multiple
                                    className="hidden" 
                                    onChange={(e) => {
                                        const newFiles = Array.from(e.target.files || []);
                                        const currentVal = Array.isArray(value) ? value : (value ? [value] : []);
                                        handleInputChange(key, [...currentVal, ...newFiles]);
                                    }}
                                    accept="image/*" 
                                />
                            </label>
                        </div>
                        {Array.isArray(value) && value.length > 0 && (
                            <div className="grid grid-cols-2 gap-2">
                                {value.map((item, idx) => (
                                    <div key={idx} className="p-2 bg-indigo-50 rounded-lg flex items-center justify-between text-[10px] text-indigo-700 font-medium overflow-hidden">
                                        <span className="truncate mr-1">
                                            {typeof item === 'string' 
                                                ? (item.startsWith('http') ? item.split('/').pop() : 'Existing Image') 
                                                : (item as File).name}
                                        </span>
                                        <button 
                                            type="button" 
                                            onClick={() => {
                                                const newVal = [...value];
                                                newVal.splice(idx, 1);
                                                handleInputChange(key, newVal.length > 0 ? newVal : null);
                                            }} 
                                            className="text-red-500 hover:text-red-700 shrink-0"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor" className="w-3 h-3">
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {!Object.values(AiModelInputType).includes(type as any) && (
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
                const isFullWidth = type === 'textarea' || type === 'image' || type === 'file' || field.fullWidth;
                return (
                    <div key={field.key} className={isFullWidth ? 'md:col-span-2' : ''}>
                        {renderField(field)}
                    </div>
                );
            })}
        </div>
    );
}
