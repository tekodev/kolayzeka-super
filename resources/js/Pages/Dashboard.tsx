import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage, useForm, Link } from '@inertiajs/react';
import { useState, useEffect, FormEventHandler, useRef } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import DynamicForm from '@/Components/Generation/DynamicForm';
import ResultDisplay from '@/Components/Generation/ResultDisplay';

// Loose typing for prototype speed
interface DashboardProps {
    auth: any;
    aiModels: any[];
    flash: {
        success?: string;
        error?: string;
        generation_result?: any;
    };
}

export default function Dashboard({ aiModels, auth, flash }: DashboardProps) {
    const user = auth.user;
    const [selectedModel, setSelectedModel] = useState<any>(aiModels.length > 0 ? aiModels[0] : null);
    const resultsRef = useRef<HTMLDivElement>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        ai_model_id: selectedModel?.id,
        input_data: {} as Record<string, any>,
    });

    useEffect(() => {
        if (selectedModel) {
            setData('ai_model_id', selectedModel.id);
            
            // Default reset logic for new array schema
            const newDefaults: Record<string, any> = {};
            const schema = selectedModel.providers[0]?.schema?.input_schema;
            
            if (Array.isArray(schema)) {
                schema.forEach((field: any) => {
                   if (field.default !== undefined) {
                       newDefaults[field.key] = field.default;
                   }
                });
            }
            
            setData('input_data', newDefaults);
        }
    }, [selectedModel]);

    // Scroll to results when they appear
    useEffect(() => {
        if (flash.generation_result && resultsRef.current) {
            resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [flash.generation_result]);

    const handleInputChange = (key: string, value: any) => {
        setData('input_data', { ...data.input_data, [key]: value });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('generate'), {
            forceFormData: true, // Crucial for file uploads
            preserveScroll: true, // Keep scroll position during processing
            onSuccess: () => {
                // Keep inputs or specific reset
            },
        });
    };

    // renderField removed as we use DynamicForm component

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    KolayZeka Hub
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    
                    {/* Status Messages */}
                    {flash.success && (
                        <div className="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                            {flash.success}
                        </div>
                    )}
                     {flash.error && (
                        <div className="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                            {flash.error}
                        </div>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {/* Sidebar: Models */}
                        <div className="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 h-fit">
                            <h3 className="font-bold text-lg mb-4 text-gray-700 dark:text-gray-200">Available Models</h3>
                            {aiModels.length === 0 ? (
                                <p className="text-gray-500 text-sm">No active models found.</p>
                            ) : (
                                <ul className="space-y-2">
                                    {aiModels.map((model) => (
                                        <li key={model.id}>
                                            <button
                                                onClick={() => setSelectedModel(model)}
                                                className={`w-full text-left px-4 py-2 rounded-lg transition-colors duration-200 flex items-center gap-2
                                                    ${selectedModel?.id === model.id 
                                                        ? 'bg-indigo-600 text-white' 
                                                        : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300'
                                                    }`}
                                            >
                                                {model.image_url && (
                                                    <img src={`/storage/${model.image_url}`} className="w-8 h-8 rounded-full object-cover bg-gray-200" alt="" />
                                                )}
                                                <span>{model.name}</span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        {/* Main Content */}
                        <div className="md:col-span-2 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                            {selectedModel ? (
                                <div>
                                    <div className="flex justify-between items-start mb-6 border-b pb-4">
                                        <div>
                                            <h3 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{selectedModel.name}</h3>
                                            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">{selectedModel.category}</p>
                                        </div>
                                        <div className="text-right">
                                            <span className="block text-sm text-gray-500">Your Credit Balance</span>
                                            <span className="block text-xl font-bold text-green-600">{user.credit_balance}</span>
                                        </div>
                                    </div>

                                    <p className="text-gray-600 dark:text-gray-300 mb-8">{selectedModel.description}</p>

                                    <form onSubmit={submit} className="space-y-6">
                                            <div className="bg-white dark:bg-gray-900/50 p-6 rounded-xl border border-gray-100 dark:border-gray-700">
                                                <h4 className="font-bold text-gray-800 dark:text-gray-200 mb-6 border-b border-gray-200 dark:border-gray-700 pb-3 flex items-center gap-2">
                                                    <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                                    </svg>
                                                    Generation Parameters
                                                </h4>
                                                
                                                {selectedModel.providers?.[0]?.schema?.input_schema ? (
                                                    <DynamicForm 
                                                        schema={selectedModel.providers[0].schema.input_schema}
                                                        data={data.input_data}
                                                        errors={errors as any}
                                                        setData={(key, val) => handleInputChange(key, val)}
                                                    />
                                                ) : (
                                                    <div className="p-4 text-center text-gray-400 italic">
                                                        No configuration available for this model.
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex items-center justify-end">
                                                <PrimaryButton disabled={processing} className="w-full justify-center py-4 text-lg font-bold rounded-xl shadow-lg shadow-indigo-100 uppercase tracking-wide h-14 transition-all hover:shadow-indigo-200">
                                                    {processing ? 'Generating...' : `Generate Magic`}
                                                </PrimaryButton>
                                            </div>
                                        </form>

                                        {/* Result Display */}
                                        <div className="mt-8" ref={resultsRef}>
                                            <ResultDisplay 
                                                generation={flash.generation_result}
                                                error={flash.error}
                                            />
                                        </div>

                                </div>
                            ) : (
                                <div className="text-center py-12">
                                    <div className="text-gray-400 mb-4">
                                        <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                    </div>
                                    <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">Get Started</h3>
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Select an AI model from the sidebar to begin creating.</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
