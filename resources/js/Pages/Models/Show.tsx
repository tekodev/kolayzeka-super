import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, Link } from '@inertiajs/react';
import React, { useState, FormEventHandler, useEffect } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import DynamicForm from '@/Components/Generation/DynamicForm';
import ResultDisplay from '@/Components/Generation/ResultDisplay';
import toast from 'react-hot-toast';

interface ModelShowProps {
    auth: any;
    aiModel: any;
    initialData?: Record<string, any>; // Data from reprompt
    repromptResult?: any; // Result from reprompt
    flash: {
        success?: string;
        error?: string;
        generation_result?: any;
    };
}

export default function Show({ aiModel, auth, flash, initialData, repromptResult }: ModelShowProps) {
    const user = auth.user;
    const provider = aiModel.providers?.[0];
    const schema = provider?.schema?.input_schema || [];
    
    // Initialize form with defaults from schema
    const defaultValues: Record<string, any> = {};
    schema.forEach((field: any) => {
        defaultValues[field.key] = field.default !== undefined ? field.default : '';
    });

    // Merge reprompt data if exists
    // Note: reprompt data keys might need casting if they came from JSON (Backend usually sends correct types though)
    const formValues = initialData ? { ...defaultValues, ...initialData } : defaultValues;

    const { data, setData, post, processing, errors, reset, transform } = useForm({
        ai_model_id: aiModel.id,
        input_data: formValues,
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
                toast.success('Generation started! You will be notified when it completes.', {
                    duration: 4000,
                    icon: '🚀'
                });
            },
            onError: () => {
                toast.error('Failed to start generation. Please check inputs.');
            }
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
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {aiModel.name}
                    </h2>
                </div>
            }
        >
            <Head title={aiModel.name} />

            <div>
                {/* Full Width Model Info Header */}
                    <div className="relative h-[350px] md:h-[300px] w-full overflow-hidden">
                        {aiModel.image_url ? (
                        <img
                            src={aiModel.image_url.startsWith('http') ? aiModel.image_url : `/storage/${aiModel.image_url}`}
                            alt={aiModel.name}
                            className="absolute inset-0 w-full h-full object-cover brightness-50 dark:brightness-30"
                        />
                        ) : (
                            <div className='absolute inset-0 w-full h-full bg-gray-900 dark:bg-black'></div>
                        )}
                            <div className="absolute inset-0 h-full flex items-center justify-start">
                            <div className="container mx-auto px-4 md:px-8 mt-6 md:mt-10 max-w-7xl flex items-center justify-between">
                                <div className="text-start">
                                    <div className="flex flex-col sm:flex-row items-start sm:items-center gap-2 mb-3 md:mb-4">
                                        <h1 className="text-lg md:text-3xl font-bold text-white">{aiModel.name}</h1>
                                    </div>
                                    <p className="text-xs md:text-sm lg:text-base text-white/90 mb-4 max-w-2xl leading-relaxed">
                                        {aiModel.description}
                                    </p>
                                    <div className='flex gap-2 flex-wrap'>
                                        {aiModel.categories?.map((category: any) => (
                                            <span key={category.id} className="px-2 py-1 md:px-3 md:py-1 text-xs rounded-full bg-indigo-100/20 dark:bg-indigo-900/40 text-indigo-100 border border-indigo-200/20 backdrop-blur-md">
                                                {category.name}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                {/* Main Content Area (Form & Result) */}
                <div className="pt-8 px-4 sm:px-6 lg:px-8 pb-16">
                    <div className="mx-auto max-w-7xl">
                        <div className="grid md:grid-cols-2 gap-6 lg:gap-8 align-top">

                            {/* Left Column: Result */}
                            <div className="rounded-2xl max-h-[calc(100vh-100px)] overflow-y-auto border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 lg:p-8 flex flex-col">
                                <div className="flex items-center justify-between mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">Result</h2>
                                    <div className="flex flex-col items-end">
                                        <span className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Your Credits</span>
                                        <span className="text-lg font-black text-green-600">{user.credit_balance}</span>
                                    </div>
                                </div>

                                <div className="flex-grow">
                                    <ResultDisplay 
                                        generation={
                                            (flash.generation_result || repromptResult) 
                                                ? { ...(flash.generation_result || repromptResult), output_type: aiModel.output_type }
                                                : null
                                        }
                                        error={flash.error}
                                    />
                                </div>
                            </div>

                            {/* Right Column: Form */}
                            <div className="rounded-2xl max-h-[calc(100vh-100px)] overflow-y-auto border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-6 lg:px-8 lg:py-8 flex flex-col">
                                <div className="flex items-center justify-between mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                        </svg>
                                        Configuration
                                    </h2>
                                    <div className="flex items-center gap-4">
                                        <Link 
                                            href={route('models.docs', aiModel.slug)}
                                            className="text-xs font-bold uppercase tracking-widest text-gray-400 hover:text-indigo-600 transition-all"
                                        >
                                            Docs
                                        </Link>
                                        <span className="px-3 py-1 text-xs border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium">
                                            {provider?.provider?.name || 'Custom'}
                                        </span>
                                    </div>
                                </div>

                                <form onSubmit={submit} className="flex flex-col flex-grow">
                                    <div className="flex-grow space-y-6">
                                        <DynamicForm 
                                            schema={schema}
                                            data={data.input_data}
                                            errors={errors as any}
                                            setData={(key, val) => handleInputChange(key, val)}
                                        />
                                    </div>

                                    <div className="sticky md:bottom-2 bottom-5 mt-8 pt-6 border-t border-gray-50 dark:border-gray-700 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm z-10">
                                        <PrimaryButton disabled={processing} className="w-full justify-center py-4 text-lg font-bold tracking-wide rounded-2xl shadow-lg shadow-indigo-100 h-14 uppercase transition-all hover:scale-[1.02]">
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
