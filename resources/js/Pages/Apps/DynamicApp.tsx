import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, Link, router as inertiaRouter } from '@inertiajs/react';
import React, { useState, useEffect, useRef, FormEvent } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import ResultDisplay from '@/Components/Generation/ResultDisplay';
import GenerationProgress from '@/Components/Generation/GenerationProgress';

import { toast } from 'react-hot-toast';
import DragDropUploader from '@/Components/Generation/DragDropUploader';
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
    slug?: string;
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
    image_url?: string;
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
    generation_ids?: Record<string | number, number>;
}

export default function DynamicApp({ auth, app }: { auth: any; app: App }) {
    const { props } = usePage();
    const flash = props.flash as any;
    const execution = flash.execution as AppExecution | undefined;
    const user = auth.user;
    
    // State for live execution tracking
    const [currentExecution, setCurrentExecution] = useState<AppExecution | null>(execution || null);
    const [isPolling, setIsPolling] = useState(false);

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
                    const url = new URL(window.location.href);
                    url.searchParams.delete('execution_id');
                    window.history.replaceState({}, '', url);
                })
                .catch(console.error);
        }
    }, [currentExecution]);

    // Active tracking
    // If waiting_approval, it means current_step (e.g. 1) is DONE, and we are READY for step 2.
    const currentStepOrder = currentExecution?.current_step || 1;
    const activeDataEntryStep = currentExecution?.status === 'waiting_approval' 
        ? Math.min(currentStepOrder + 1, app.steps.length)
        : currentStepOrder;

    const [viewedStepOrder, setViewedStepOrder] = useState<number>(activeDataEntryStep);

    useEffect(() => {
        // Only auto-switch if we don't have a current execution or if it's a fresh load
        // Actually, we want to stay where we are unless the user explicitly navigates
        // But on initial load/status update to 'waiting_approval', moving to next step is often desired
        // However, user said "stay on step 1 result", so we will only auto-set on first load
        if (!currentExecution) {
            setViewedStepOrder(1);
        }
    }, []);

    // Helper to get step name with fallback
    const getStepName = (step: any) => step.name || `Adım ${step.order}`;

    const computeFieldsForStep = (stepOrder: number) => {
        const step = app.steps.find(s => s.order === stepOrder);
        if (!step) return [];

        const getUserConfigByInputKey = (inputKey: string) =>
            Object.values(step.config || {}).find(cfg => cfg?.source === 'user' && cfg?.input_key === inputKey);

        const uiFields = (step.ui_schema && step.ui_schema.length > 0)
            ? step.ui_schema
                .filter(field => {
                    const fieldConfig = step.config?.[field.key];
                    return fieldConfig?.source !== 'static' && fieldConfig?.source !== 'previous';
                })
                .map(field => ({
                    ...field,
                    stepOrder: step.order,
                    inputKey: field.key,
                    defaultValue: step.config?.[field.key]?.value
                        ?? getUserConfigByInputKey(field.key)?.value
                        ?? field.default
                }))
            : [];

        const uiKeys = new Set(uiFields.map(f => f.inputKey));

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
                section: step.name || (f as any).section || `Step ${step.order} Settings`,
                defaultValue: step.config?.[f.key]?.value ?? f.default
            }));

        return [...uiFields, ...modelFields] as (UiField & { stepOrder: number; inputKey: string; defaultValue: any })[];
    };

    const activeFormFields = computeFieldsForStep(activeDataEntryStep);
    const viewedFields = computeFieldsForStep(viewedStepOrder);

    const sections: Record<string, any[]> = {};
    viewedFields.forEach(field => {
        const secName = field.section || `Settings`;
        if (!sections[secName]) sections[secName] = [];
        sections[secName].push(field);
    });

    // We only use data from useForm for the ACTIVE entry step
    const getInitialDataForStep = (stepOrder: number) => {
        const fields = computeFieldsForStep(stepOrder);
        const initialDetails: Record<string, any> = {};
        fields.forEach(field => {
            if (field.type === 'image' || field.type === 'file') initialDetails[field.inputKey] = null;
            else if (field.type === 'images' || field.type === 'files') initialDetails[field.inputKey] = [];
            else initialDetails[field.inputKey] = field.defaultValue ?? '';
        });
        return initialDetails;
    };

    const getInitialData = () => getInitialDataForStep(activeDataEntryStep);

    // Note: useForm initialData won't dynamically update purely by ref, but we don't need it to. 
    // It will stay tracking whatever the current step form entries are. Since we redirect or update the execution
    // State when moving to next step, inertia will re-mount the component if visiting a fresh step anyway,
    // or we can use effect to update data if needed.
    const { data, setData, post, processing, errors, progress } = useForm(getInitialData());

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

    // Re-initialize form defaults when user switches viewed step
    useEffect(() => {
        const newDefaults = getInitialDataForStep(viewedStepOrder);
        Object.entries(newDefaults).forEach(([key, val]) => {
            // Only set if the field isn't already set (user may have typed something)
            if (data[key] === undefined || data[key] === '' || data[key] === null) {
                setData(key as any, val);
            }
        });
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [viewedStepOrder]);

    useEffect(() => {
        if (flash.execution && resultsRef.current) {
            resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [flash.execution?.id]);

    const handleApprove = (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!currentExecution) return;
        
        inertiaRouter.post(route('apps.execution.approve', currentExecution.id), data, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updated = (page.props.flash as any).execution;
                if (updated) setCurrentExecution(updated);
            }
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if(viewedStepOrder !== activeDataEntryStep) {
            toast.error("Can only submit active step.");
            return;
        }

        // If execution is failed, re-approve to retry the step that failed
        // (keeps Step 1 result and just re-runs the failed step)
        if (currentExecution && currentExecution.status === 'failed') {
            // Restore to waiting_approval state so we can re-approve the step
            inertiaRouter.post(route('apps.execution.approve', currentExecution.id), data, {
                preserveScroll: true,
                onSuccess: (page) => {
                    const updated = (page.props.flash as any).execution;
                    if (updated) {
                        setCurrentExecution(updated);
                        toast.success('Retrying...');
                    }
                },
                onError: () => toast.error('Could not retry. Please try again.')
            });
            return;
        }
        
        // Show loading via processing state automatically
        post(route('apps.execute', app.slug), {
            forceFormData: true, // Need this for file uploads
            preserveScroll: true,
            onSuccess: (page) => {
                const newEx = (page.props.flash as any).execution;
                if (newEx) {
                    setCurrentExecution(newEx);
                    toast.success('Process started! You will be notified when complete.');
                }
            },
            onError: (err) => {
                toast.error('Could not start application. Please check your inputs.');
            }
        });
    };

    const isEditable = viewedStepOrder === activeDataEntryStep;

    const renderInput = (field: any) => {
        // Values for controlled inputs: 
        // if editable, it comes from our useForm `data`
        // if not, it comes from the snapshot in `currentExecution.inputs`
        const value = isEditable 
            ? data[field.inputKey] ?? '' 
            : currentExecution?.inputs?.[field.inputKey] ?? '';

        const handleChange = (val: any) => {
            if (isEditable) setData(field.inputKey, val);
        };

        const bgClass = isEditable 
            ? "bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white"
            : "bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 opacity-60 cursor-not-allowed";

        const textInputClasses = `mt-1 block w-full rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm transition-all ${bgClass}`;

        switch (field.type) {
            case 'select':
                return (
                    <select
                        className={textInputClasses}
                        value={value}
                        onChange={e => handleChange(e.target.value)}
                        disabled={!isEditable}
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
                            className={`flex-grow h-2 rounded-lg appearance-none cursor-pointer accent-indigo-600 ${!isEditable ? 'opacity-50 cursor-not-allowed' : 'bg-gray-200 dark:bg-gray-700'}`}
                            value={value}
                            onChange={e => handleChange(parseInt(e.target.value))}
                            disabled={!isEditable}
                        />
                        <span className="text-sm font-bold text-indigo-600 dark:text-indigo-400 w-12 text-right">{value}{field.unit || '%'}</span>
                    </div>
                );
            case 'image':
            case 'file':
                return (
                    <div className={!isEditable ? "pointer-events-none opacity-60" : ""}>
                    <DragDropUploader 
                        fieldKey={field.inputKey}
                        value={value}
                        onChange={(val: any) => handleChange(val)}
                        isMultiple={false}
                        accept={field.type === 'image' ? "image/*" : "*/*"}
                    />
                    </div>
                );
            case 'images':
            case 'files':
                return (
                    <div className={!isEditable ? "pointer-events-none opacity-60" : ""}>
                    <DragDropUploader 
                        fieldKey={field.inputKey}
                        value={value}
                        onChange={(val: any) => handleChange(val)}
                        isMultiple={true}
                        accept={field.type === 'images' ? "image/*" : "*/*"}
                    />
                    </div>
                );
            case 'textarea':
                return (
                    <textarea
                        value={value}
                        onChange={e => handleChange(e.target.value)}
                        className={`${textInputClasses} min-h-[100px]`}
                        placeholder={field.placeholder || field.label}
                        disabled={!isEditable}
                    />
                );
            default:
                return (
                    <TextInput
                        type={field.type === 'number' || field.type === 'integer' ? 'number' : 'text'}
                        value={value}
                        onChange={e => handleChange(field.type === 'number' || field.type === 'integer' ? parseFloat(e.target.value) : e.target.value)}
                        className={textInputClasses}
                        placeholder={field.placeholder || field.label}
                        disabled={!isEditable}
                    />
                );
        }
    };

    const viewedStep = app.steps.find(s => s.order === viewedStepOrder);

    // Determines the result to display in the Right Column
    const resultToDisplay = currentExecution?.history?.[viewedStepOrder];
    // Determines if the current viewed step is currently processing
    const isViewedProcessing = currentExecution?.status === 'processing' && currentExecution?.current_step === viewedStepOrder;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href={route('apps.index')} className="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {app.name}
                    </h2>
                </div>
            }
        >
            <Head title={app.name} />

            <div>
                {/* Full Width Info Header (Like Show.tsx) */}
                <div className="relative h-[350px] md:h-[300px] w-full overflow-hidden">
                    {app.image_url ? (
                        <img
                            src={app.image_url.startsWith('http') ? app.image_url : `/storage/${app.image_url}`}
                            alt={app.name}
                            className="absolute inset-0 w-full h-full object-cover brightness-50 dark:brightness-30"
                        />
                    ) : (
                        <div className="absolute inset-0 w-full h-full bg-gradient-to-br from-indigo-700 to-indigo-900 border-b border-indigo-800"></div>
                    )}
                    <div className="absolute inset-0 h-full flex items-center justify-start">
                        <div className="container mx-auto px-4 md:px-8 mt-6 md:mt-10 max-w-7xl">
                            <h1 className="text-lg md:text-3xl font-bold text-white mb-3">{app.name}</h1>
                            <p className="text-xs md:text-sm lg:text-base text-white/90 max-w-2xl leading-relaxed">
                                {app.description}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Tabbed Navigation Layout for Multi-Step */}
                {app.steps.length > 1 && (
                    <div className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 w-full shadow-sm relative z-10">
                        <div className="container mx-auto px-4 sm:px-6 lg:px-8 max-w-7xl">
                            <nav className="-mb-px flex gap-6 overflow-x-auto hide-scrollbar" aria-label="Tabs">
                                {app.steps.sort((a,b)=>a.order-b.order).map((step) => {
                                    const isActive = viewedStepOrder === step.order;
                                    // Highlight past ones as "done" unless they failed
                                    const isDone = currentExecution?.history?.[step.order] && currentExecution?.history?.[step.order]?.status !== 'failed';
                                    
                                    return (
                                        <button
                                            key={step.id}
                                            onClick={() => setViewedStepOrder(step.order)}
                                            className={`
                                                whitespace-nowrap border-b-2 py-4 px-2 text-sm font-bold tracking-widest uppercase transition-all flex items-center gap-2
                                                ${isActive 
                                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                                                }
                                            `}
                                        >
                                            {isDone && (
                                                <svg className="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" />
                                                </svg>
                                            )}
                                            {getStepName(step)}
                                        </button>
                                    );
                                })}
                            </nav>
                        </div>
                    </div>
                )}

                {/* Main Content Area (Form & Result) */}
                <div className="pt-8 px-4 sm:px-6 lg:px-8 pb-16">
                    <div className="mx-auto max-w-7xl">
                        <div className="grid md:grid-cols-2 gap-6 lg:gap-8 align-top">
                            
                                {/* Left Column: Result */}
                                <div className="rounded-2xl max-h-[calc(100vh-100px)] overflow-y-auto border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 lg:p-8 flex flex-col" ref={resultsRef}>
                                    <div className="flex items-center justify-between mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                                        <h2 className="text-xl font-bold text-gray-900 dark:text-white">Result</h2>
                                        <div className="flex flex-col items-end">
                                            <span className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Your Credits</span>
                                            <span className="text-lg font-black text-green-600">{user.credit_balance}</span>
                                        </div>
                                    </div>

                                    <div className="flex-grow">
                                        {(flash.error || (currentExecution?.status === 'failed' && currentExecution?.history?.error_message)) && (
                                            <div className="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-6 rounded-2xl shadow-sm mb-6 animate-fade-in-up">
                                                <div className="flex gap-4">
                                                    <div className="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center shrink-0">
                                                        <svg className="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <p className="text-sm text-red-700 dark:text-red-400 font-bold uppercase tracking-widest mb-1">İşlem Hatası</p>
                                                        <p className="text-sm text-red-600 dark:text-red-400 leading-relaxed">{flash.error || currentExecution?.history?.error_message}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        )}

                                        {isViewedProcessing ? (
                                            <GenerationProgress processing={true} progressPercentage={progress?.percentage || 0} />
                                        ) : resultToDisplay ? (
                                            <ResultDisplay 
                                                generation={{ 
                                                    ...resultToDisplay, 
                                                    id: currentExecution?.generation_ids?.[viewedStepOrder],
                                                    status: resultToDisplay.status || 'completed', 
                                                    output_data: resultToDisplay,
                                                    ai_model: viewedStep?.ai_model,
                                                    created_at: resultToDisplay?.created_at || currentExecution?.history?.created_at || new Date().toISOString()
                                                }}
                                                onCreateVideo={viewedStepOrder === 1 && currentExecution?.status === 'waiting_approval' ? () => setViewedStepOrder(2) : undefined}
                                            />
                                        ) : viewedStepOrder > currentStepOrder ? (
                                            <div className="flex flex-col items-center justify-center h-full min-h-[300px] text-gray-400 dark:text-gray-500">
                                                <svg className="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <p className="font-medium text-center">Complete previous steps first<br/>to generate this result.</p>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col items-center justify-center h-full min-h-[300px] text-gray-400 dark:text-gray-500">
                                                <svg className="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                <p className="font-medium">Ready for Magic</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                                {/* Left Column: Form */}
                                <div className="rounded-2xl max-h-[calc(100vh-100px)] overflow-y-auto border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 px-6 py-6 lg:px-8 lg:py-8 flex flex-col relative">
                                    <div className="flex items-center justify-between mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        <svg className="w-5 h-5 text-indigo-500 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                        </svg>
                                        {app.steps.length > 1 ? `${getStepName(viewedStep)} - Configuration` : 'Configuration'}
                                    </h2>
                                    <Link
                                        href={route('apps.docs', app.slug)}
                                        className="flex items-center gap-1.5 text-xs font-semibold text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        Docs
                                    </Link>
                                </div>

                                    <form onSubmit={handleSubmit} className="flex flex-col flex-grow relative">
                                        <div className="flex-grow space-y-8">
                                            {Object.entries(sections).map(([secName, fields]) => (
                                                <div key={secName}>
                                                    {Object.keys(sections).length > 1 && (
                                                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-4">{secName}</h3>
                                                    )}
                                                    <div className="space-y-5">
                                                        {fields.map(field => (
                                                            <div key={field.inputKey}>
                                                                <div className="flex items-center justify-between mb-1">
                                                                    <InputLabel value={field.label} className="text-[11px] uppercase font-bold tracking-[0.1em] text-gray-500 dark:text-gray-400" />
                                                                    {field.required && <span className="text-[9px] text-red-500 font-bold uppercase tracking-widest">Zorunlu</span>}
                                                                </div>
                                                                {renderInput(field)}
                                                                {field.description && <p className="text-[10px] text-gray-400 dark:text-gray-500 mt-1 italic leading-relaxed">{field.description}</p>}
                                                                {isEditable && errors[field.inputKey] && <InputError message={errors[field.inputKey]} className="mt-2" />}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                            
                                            {viewedFields.length === 0 && (
                                                <div className="p-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                                                    <p className="text-gray-500 dark:text-gray-400 italic">No user inputs required for this step.</p>
                                                </div>
                                            )}
                                        </div>

                                        <div className="sticky md:bottom-2 bottom-5 mt-8 pt-6 border-t border-gray-50 dark:border-gray-700 bg-white/95 dark:bg-gray-800/95 backdrop-blur-sm z-10">
                                            {currentExecution?.status === 'waiting_approval' && viewedStepOrder === activeDataEntryStep ? (
                                                <PrimaryButton type="button" onClick={handleApprove} disabled={processing || isPolling} className="w-full justify-center py-4 text-lg font-bold tracking-wide rounded-2xl shadow-lg shadow-indigo-100 h-14 uppercase transition-all hover:scale-[1.02]">
                                                    {processing || isPolling ? 'Processing...' : `Approve & Generate Next`}
                                                </PrimaryButton>
                                            ) : (
                                                <PrimaryButton 
                                                    type="submit" 
                                                    disabled={processing || isPolling || viewedStepOrder > activeDataEntryStep} 
                                                    className={`w-full justify-center py-4 text-lg font-bold tracking-wide rounded-2xl shadow-lg h-14 uppercase transition-all 
                                                        ${viewedStepOrder > activeDataEntryStep 
                                                            ? 'bg-gray-400 hover:bg-gray-400 cursor-not-allowed opacity-50' 
                                                            : 'shadow-indigo-100 hover:scale-[1.02]'}`}
                                                >
                                                    {processing || isPolling ? (
                                                        <span className="flex items-center gap-2">
                                                            <svg className="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                            </svg>
                                                            Processing...
                                                        </span>
                                                    ) : (
                                                        viewedStepOrder < activeDataEntryStep ? 'Regenerate This Step' : 'Create Generation'
                                                    )}
                                                </PrimaryButton>
                                            )}
                                        </div>
                                    </form>
                                </div>

                            </div>
                    </div>
                </div>
            </div>
            
            {/* Styles definition similar to Show.tsx if needed */}
            <style dangerouslySetInnerHTML={{__html: `
                @keyframes fade-in-up {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-fade-in-up {
                    animation: fade-in-up 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                .hide-scrollbar::-webkit-scrollbar {
                    display: none;
                }
                .hide-scrollbar {
                    -ms-overflow-style: none;  /* IE and Edge */
                    scrollbar-width: none;  /* Firefox */
                }
            `}} />
        </AuthenticatedLayout>
    );
}

