import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, Link } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import ResultDisplay from '@/Components/Generation/ResultDisplay';
import GenerationProgress from '@/Components/Generation/GenerationProgress';
import AppDocs from '@/Components/Generation/AppDocs';
import { toast } from 'react-hot-toast';
import { router as inertiaRouter } from '@inertiajs/react';

// Define types for App and Steps
interface AiModelSchema {
    input_schema: Array<{
        key: string;
        type: string;
        label: string;
        options?: any[];
        default?: any;
    }>;
}

interface AiModel {
    id: number;
    name: string;
    output_type?: string;
    schema: AiModelSchema;
}

interface UiField {
    key: string;
    type: string;
    label: string;
    section?: string;
    options?: any[];
    default?: any;
    min?: number;
    max?: number;
    step?: number;
    placeholder?: string;
    description?: string;
    required?: boolean;
}

interface AppStep {
    id: number;
    name: string;
    order: number;
    config: Record<string, { source: string; value?: any; label?: string; input_key?: string }>;
    ui_schema?: UiField[];
    ai_model: AiModel;
}

interface App {
    id: number;
    name: string;
    slug: string;
    description: string;
    steps: AppStep[];
}

interface AppExecution {
    id: number;
    status: string;
    current_step: number;
    history: Record<string, any>; // keyed by step order
    inputs: Record<string, any>;
    app_id: number;
}

export default function DynamicApp({ auth, app }: { auth: any; app: App }) {
    const { props } = usePage();
    const flash = props.flash as any;
    const execution = flash.execution as AppExecution | undefined;
    
    // State for live execution tracking
    const [currentExecution, setCurrentExecution] = useState<AppExecution | null>(execution || null);
    const [isPolling, setIsPolling] = useState(false);
    const [activeTab, setActiveTab] = useState<'config' | 'docs'>('config');
    const resultsRef = useRef<HTMLDivElement>(null);

    // Initial load from URL for notification clicks
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const executionId = urlParams.get('execution_id');
        if (executionId && !currentExecution) {
            fetch(route('apps.execution.status', executionId))
                .then(res => res.json())
                .then((data: AppExecution) => {
                    setCurrentExecution(data);
                    // Clear the param without refreshing
                    const url = new URL(window.location.href);
                    url.searchParams.delete('execution_id');
                    window.history.replaceState({}, '', url);
                })
                .catch(console.error);
        }
    }, []);
    const fieldsToRender = app.steps.flatMap(step => {
        const getUserConfigByInputKey = (inputKey: string) =>
            Object.values(step.config || {}).find(cfg => cfg?.source === 'user' && cfg?.input_key === inputKey);

        const uiFields = (step.ui_schema && step.ui_schema.length > 0)
            ? step.ui_schema.map(field => ({
                ...field,
                stepOrder: step.order,
                inputKey: field.key,
                defaultValue: step.config?.[field.key]?.value
                    ?? getUserConfigByInputKey(field.key)?.value
                    ?? field.default
            }))
            : [];

        const uiKeys = new Set(uiFields.map(f => f.inputKey));

        // Fallback or Addition: include fields from model schema where source is 'user'
        const modelFields = (step.ai_model.schema?.input_schema || [])
            .filter(f => {
                const fieldConfig = step.config?.[f.key];
                return fieldConfig && fieldConfig.source === 'user' && !uiKeys.has(f.key);
            })
            .map(f => ({
                ...f,
                label: step.config?.[f.key]?.label || f.label,
                stepOrder: step.order,
                inputKey: f.key,
                section: (f as any).section || 'General Settings',
                defaultValue: step.config?.[f.key]?.value ?? f.default
            }));

        return [...uiFields, ...modelFields];
    }) as (UiField & { stepOrder: number; inputKey: string; defaultValue: any })[];

    const sections: Record<string, any[]> = {};
    fieldsToRender.forEach(field => {
        const secName = field.section || 'General Settings';
        if (!sections[secName]) sections[secName] = [];
        sections[secName].push(field);
    });

    // Initialize Form Data
    const initialData: Record<string, any> = {};
    fieldsToRender.forEach(field => {
        if (field.type === 'image' || field.type === 'file') initialData[field.inputKey] = null;
        else if (field.type === 'images' || field.type === 'files') initialData[field.inputKey] = [];
        else initialData[field.inputKey] = field.defaultValue ?? '';
    });

    const { data, setData, post, processing, errors, reset, progress } = useForm(initialData);

    // Polling logic
    useEffect(() => {
        if (!currentExecution) return;
        const needsPolling = ['pending', 'processing'].includes(currentExecution.status);
        if (needsPolling) {
            setIsPolling(true);
            const interval = setInterval(() => {
                fetch(route('apps.execution.status', currentExecution.id))
                    .then(res => res.json())
                    .then((updated: AppExecution) => {
                        setCurrentExecution(updated);
                        if (['completed', 'failed', 'waiting_approval', 'waiting_inputs'].includes(updated.status)) setIsPolling(false);
                    }).catch(console.error);
            }, 3000);
            return () => clearInterval(interval);
        } else {
            setIsPolling(false);
        }
    }, [currentExecution?.status, currentExecution?.id]);

    // Scroll to results
    useEffect(() => {
        if (flash.execution && resultsRef.current) {
            resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [flash.execution?.id]);

    const handleApprove = () => {
        if (!currentExecution) return;
        inertiaRouter.post(route('apps.execution.approve', currentExecution.id), {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = (page.props.flash as any).execution;
                if (updated) setCurrentExecution(updated);
            }
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        toast.promise(
            new Promise((resolve, reject) => {
                post(route('apps.execute', app.slug), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const newEx = (page.props.flash as any).execution;
                        if (newEx) {
                            setCurrentExecution(newEx);
                            resolve(newEx);
                        } else {
                            reject(new Error('Failed to start execution'));
                        }
                    },
                    onError: (err) => reject(err)
                });
            }),
            {
                loading: 'Initializing application pipeline...',
                success: 'Process started! You will be notified when complete.',
                error: 'Could not start application. Please check your inputs.',
            }
        );
    };

    const renderInput = (field: any) => {
        switch (field.type) {
            case 'select':
                return (
                    <select
                        className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm text-sm"
                        value={data[field.inputKey]}
                        onChange={e => setData(field.inputKey, e.target.value)}
                    >
                        <option value="">Select option...</option>
                        {field.options?.map((opt: any) => (
                            <option key={opt.value ?? opt} value={opt.value ?? opt}>
                                {opt.label ?? opt.value ?? opt}
                            </option>
                        ))}
                    </select>
                );
            case 'range':
                return (
                    <div className="flex items-center gap-4">
                        <input 
                            type="range" 
                            min={field.min ?? 0} max={field.max ?? 100} step={field.step ?? 1}
                            className="flex-grow h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                            value={data[field.inputKey]}
                            onChange={e => setData(field.inputKey, parseInt(e.target.value))}
                        />
                        <span className="text-sm font-bold text-indigo-600 w-12 text-right">{data[field.inputKey]}{field.unit || '%'}</span>
                    </div>
                );
            case 'image':
            case 'file':
                return (
                    <div className="mt-1">
                        <div className="flex items-center justify-center w-full">
                            <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-100 border-dashed rounded-xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                                <div className="flex flex-col items-center justify-center pt-5 pb-6 text-gray-400">
                                    <svg className="w-8 h-8 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <p className="text-xs">{(data[field.inputKey] as File)?.name || 'Click or drag'}</p>
                                </div>
                                <input 
                                    type="file" 
                                    className="hidden" 
                                    onChange={e => e.target.files && setData(field.inputKey, e.target.files[0])}
                                />
                            </label>
                        </div>
                    </div>
                );
            case 'images':
            case 'files':
                return (
                    <input 
                        type="file" multiple
                        className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        onChange={e => e.target.files && setData(field.inputKey, Array.from(e.target.files))}
                    />
                );
            default:
                return (
                    <TextInput
                        value={data[field.inputKey]}
                        onChange={e => setData(field.inputKey, e.target.value)}
                        className="mt-1 block w-full border-gray-300 rounded-xl text-sm"
                        placeholder={field.placeholder || field.label}
                    />
                );
        }
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">{app.name}</h2>}>
            <Head title={app.name} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* LEFT SECTION (Form or Docs) */}
                        <div className="lg:col-span-2 space-y-8">
                            
                            {/* Header & Description */}
                            <div className="bg-white p-8 shadow-xl sm:rounded-2xl border border-gray-100 relative overflow-hidden group">
                                <div className="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 bg-indigo-50 rounded-full blur-3xl group-hover:bg-indigo-100 transition-all duration-500"></div>
                                <div className="relative z-10">
                                    <div className="flex items-center gap-4 mb-4">
                                        <div className="w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl flex items-center justify-center shadow-lg text-white">
                                            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h1 className="text-3xl font-black text-gray-900 leading-none mb-2">{app.name}</h1>
                                            <p className="text-gray-500 text-sm italic">{app.description}</p>
                                        </div>
                                    </div>
                                    
                                    {/* Tab Navigation */}
                                    <div className="flex border-b border-gray-50 mt-8 mb-2">
                                        <button 
                                            onClick={() => setActiveTab('config')}
                                            className={`px-6 py-3 text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'config' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'}`}
                                        >
                                            Configuration
                                        </button>
                                        <button 
                                            onClick={() => setActiveTab('docs')}
                                            className={`px-6 py-3 text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'docs' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'}`}
                                        >
                                            Documentation
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {activeTab === 'config' ? (
                                <div className="bg-white overflow-hidden shadow-xl sm:rounded-2xl p-8 border border-gray-100">
                                    <form onSubmit={handleSubmit} className="space-y-10">
                                        {Object.entries(sections).map(([secName, fields], idx) => (
                                            <div key={secName} className="space-y-6">
                                                <h3 className="text-lg font-black text-gray-900 border-b-2 border-indigo-50 pb-2 flex items-center gap-3">
                                                    <span className="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs">
                                                        {idx + 1}
                                                    </span>
                                                    {secName}
                                                </h3>
                                                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 p-2">
                                                    {fields.map(field => (
                                                        <div key={field.inputKey} className={field.type === 'images' || field.type === 'files' || field.fullWidth ? 'md:col-span-2' : ''}>
                                                            <div className="flex items-center justify-between mb-2">
                                                                <InputLabel value={field.label} className="text-xs uppercase font-black tracking-[0.1em] text-gray-400" />
                                                                {field.required && <span className="text-[9px] text-red-500 font-black uppercase tracking-widest">Required</span>}
                                                            </div>
                                                            {renderInput(field)}
                                                            {field.description && <p className="text-[10px] text-gray-400 mt-2 italic leading-relaxed">{field.description}</p>}
                                                            <InputError message={errors[field.inputKey]} className="mt-2" />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}

                                        <div className="pt-6 border-t border-gray-50">
                                            <PrimaryButton disabled={processing} className="w-full justify-center py-5 text-xl font-black rounded-2xl shadow-xl shadow-indigo-100 ring-4 ring-indigo-50 transition-all hover:scale-[1.01] active:scale-[0.99] uppercase tracking-tighter italic">
                                                {processing ? (
                                                    <span className="flex items-center gap-3">
                                                        <svg className="animate-spin h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                        </svg>
                                                        MAGIC IN PROGRESS...
                                                    </span>
                                                ) : `CREATE ${app.name.toUpperCase()}`}
                                            </PrimaryButton>
                                        </div>
                                    </form>
                                </div>
                            ) : (
                                <AppDocs app={app} fields={fieldsToRender} />
                            )}
                        </div>

                        {/* RIGHT SECTION (Results) */}
                        <div className="lg:col-span-1">
                            <div className="sticky top-6 space-y-6" ref={resultsRef}>
                                {flash.error && (
                                    <div className="bg-red-50 border-l-4 border-red-500 p-6 rounded-2xl shadow-sm animate-shake">
                                        <div className="flex gap-4">
                                            <div className="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                                                <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            </div>
                                            <p className="text-sm text-red-700 font-medium pt-2">{flash.error}</p>
                                        </div>
                                    </div>
                                )}

                                {(processing || isPolling) && (
                                    <div className="bg-white p-8 rounded-3xl shadow-2xl border border-indigo-50 animate-pulse">
                                        <GenerationProgress processing={processing || isPolling} progressPercentage={progress?.percentage} />
                                    </div>
                                )}

                                {currentExecution && !(processing || isPolling) && (
                                    <div className="space-y-8 animate-fade-in-up">
                                        {app.steps.map(step => {
                                            const result = currentExecution.history?.[step.order];
                                            if (!result) return null;
                                            return (
                                                <div key={step.id}>
                                                    <div className="flex items-center gap-2 mb-3 px-4">
                                                        <span className="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.5)]"></span>
                                                        <span className="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                                            {step.name} Complete
                                                        </span>
                                                    </div>
                                                    <ResultDisplay 
                                                        generation={{ 
                                                            ...result, 
                                                            status: 'completed', 
                                                            output_data: result,
                                                            ai_model: step.ai_model,
                                                            created_at: result?.created_at || new Date().toISOString()
                                                        }}
                                                    />
                                                </div>
                                            );
                                        })}

                                        {currentExecution.status === 'waiting_approval' && (
                                            <div className="bg-indigo-50 border-2 border-indigo-200 p-8 rounded-3xl shadow-xl space-y-6 text-center animate-pulse-subtle">
                                                <div className="mx-auto w-16 h-16 bg-indigo-600 text-white rounded-2xl flex items-center justify-center shadow-lg mb-4">
                                                    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h4 className="text-xl font-black text-indigo-900 uppercase italic tracking-tighter">Review & Proceed</h4>
                                                    <p className="text-indigo-600 text-xs font-bold mt-1">Please approve the current results to continue to the next step.</p>
                                                </div>
                                                <PrimaryButton 
                                                    onClick={handleApprove}
                                                    className="w-full justify-center py-4 text-lg font-black rounded-xl bg-indigo-600 border-none shadow-lg hover:bg-indigo-700 transition-all uppercase italic"
                                                >
                                                    Approve & Continue
                                                </PrimaryButton>
                                                <p className="text-[10px] text-indigo-400 font-bold uppercase tracking-widest">or generate again to refine results</p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {!currentExecution && !(processing || isPolling) && (
                                    <div className="bg-gradient-to-br from-indigo-500 to-indigo-800 rounded-3xl p-10 text-center shadow-2xl relative overflow-hidden group">
                                        <div className="absolute top-0 right-0 -mr-20 -mt-20 w-80 h-80 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all duration-700"></div>
                                        <div className="relative z-10">
                                            <div className="mx-auto w-20 h-20 bg-white/20 backdrop-blur-2xl rounded-3xl flex items-center justify-center shadow-inner mb-8 transition-transform group-hover:rotate-12 duration-500">
                                                <svg className="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                                </svg>
                                            </div>
                                            <h4 className="text-white text-2xl font-black mb-3 uppercase tracking-tight italic">Ready for Magic</h4>
                                            <p className="text-indigo-100 text-sm opacity-80 leading-relaxed font-medium px-6">
                                                Configure the settings to generate a masterpiece powered by KolayZeka AI.
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Styles for Shake Animation */}
            <style>{`
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-4px); }
                    75% { transform: translateX(4px); }
                }
                .animate-shake {
                    animation: shake 0.4s ease-in-out infinite;
                    animation-iteration-count: 2;
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
