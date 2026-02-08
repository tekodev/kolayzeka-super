import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, Link } from '@inertiajs/react';
import React, { useState, FormEventHandler, useEffect } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import DynamicForm from '@/Components/Generation/DynamicForm';
import ResultDisplay from '@/Components/Generation/ResultDisplay';

interface ModelShowProps {
    auth: any;
    aiModel: any;
    flash: {
        success?: string;
        error?: string;
        generation_result?: any;
    };
}

export default function Show({ aiModel, auth, flash }: ModelShowProps) {
    const user = auth.user;
    const provider = aiModel.providers?.[0];
    const schema = provider?.schema?.input_schema || [];
    
    // Initialize form with defaults from schema
    const initialData: Record<string, any> = {};
    schema.forEach((field: any) => {
        initialData[field.key] = field.default !== undefined ? field.default : '';
    });

    const { data, setData, post, processing, errors, reset, transform } = useForm({
        ai_model_id: aiModel.id,
        input_data: initialData,
    });

    // Inertia useForm helper for dynamic nested objects
    const handleInputChange = (key: string, value: any) => {
        setData('input_data', {
            ...data.input_data,
            [key]: value
        });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        
        // Use transform because backend expects flat structure in input_data if possible
        // but our GenerationController web route expects 'input_data' array.
        // Actually web GenerationController does: $request->input_data
        
        post(route('generate'), {
            forceFormData: true, // Crucial for file uploads
            onSuccess: () => {
                // Scroll to result or handle success
            },
        });
    };

    // renderField removed as we use DynamicForm component

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href={route('models.index')} className="text-gray-400 hover:text-indigo-600 transition-colors">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {aiModel.name}
                    </h2>
                </div>
            }
        >
            <Head title={aiModel.name} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        {/* Sidebar: Model Info & Description */}
                        <div className="space-y-6">
                            <div className="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 p-8">
                                <div className="aspect-square w-full rounded-xl overflow-hidden mb-6 bg-gray-50">
                                    {aiModel.image_url ? (
                                        <img src={aiModel.image_url.startsWith('http') ? aiModel.image_url : `/storage/${aiModel.image_url}`} className="w-full h-full object-cover" alt="" />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center text-gray-300">
                                            <svg className="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    )}
                                </div>
                                <h3 className="text-xl font-bold text-gray-900 uppercase tracking-tight">{aiModel.name}</h3>
                                <p className="text-indigo-600 text-sm font-bold mt-1">{aiModel.category}</p>
                                <p className="mt-4 text-gray-600 text-sm leading-relaxed">
                                    {aiModel.description}
                                </p>
                                
                                <div className="mt-8 pt-8 border-t border-gray-100 space-y-4">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-gray-500">Provider</span>
                                        <span className="font-bold text-gray-900">{provider?.provider?.name || 'Custom'}</span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-sm text-gray-500">Your Credits</span>
                                        <span className="text-lg font-black text-green-600">{user.credit_balance}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Main Content: Form & Result */}
                        <div className="lg:col-span-2 space-y-8">
                            
                            {/* Generation Form */}
                            <div className="bg-white shadow-sm sm:rounded-2xl border border-gray-100 p-8">
                                <h3 className="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                                    <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                    </svg>
                                    Configuration
                                </h3>

                                <form onSubmit={submit} className="space-y-8">
                                    <DynamicForm 
                                        schema={schema}
                                        data={data.input_data}
                                        errors={errors as any}
                                        setData={(key, val) => handleInputChange(key, val)}
                                    />

                                    <div className="pt-6 border-t border-gray-50">
                                        <PrimaryButton disabled={processing} className="w-full justify-center py-4 text-lg font-bold tracking-wide rounded-2xl shadow-lg shadow-indigo-100 h-14 uppercase">
                                            {processing ? (
                                                <span className="flex items-center gap-2">
                                                    <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Processing...
                                                </span>
                                            ) : (
                                                'Create Generation'
                                            )}
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>

                            {/* Result Display */}
                            <div className="mt-8">
                                <ResultDisplay 
                                    generation={flash.generation_result}
                                    error={flash.error}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

// Global tailwind additions for effects
const style = document.createElement('style');
style.innerHTML = `
@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fade-in-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
`;
document.head.appendChild(style);
