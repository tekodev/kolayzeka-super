import React, { useState } from 'react';

interface DragDropUploaderProps {
    fieldKey: string;
    value: any;
    onChange: (value: any) => void;
    isMultiple?: boolean;
    accept?: string;
}

export default function DragDropUploader({ fieldKey, value, onChange, isMultiple = false, accept = '*/*' }: DragDropUploaderProps) {
    const [dragActive, setDragActive] = useState<boolean>(false);

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            if (isMultiple) {
                const newFiles = Array.from(e.dataTransfer.files);
                const currentVal = Array.isArray(value) ? value : (value ? [value] : []);
                onChange([...currentVal, ...newFiles]);
            } else {
                onChange(e.dataTransfer.files[0]);
            }
        }
    };

    const renderFilePreview = (fileItem: any) => {
        if (!fileItem) return null;
        const isUrl = typeof fileItem === 'string';
        let url = '';
        let name = '';

        try {
            url = isUrl ? fileItem : URL.createObjectURL(fileItem);
            name = isUrl ? fileItem.split('/').pop() || 'Existing Image' : fileItem.name;
        } catch (error) {
            console.error("Error creating Object URL", error);
            return null;
        }
        
        return (
            <div className="relative group flex items-center p-2 gap-3 transition-all">
                <div className="w-12 h-12 shrink-0 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                    {(isUrl || fileItem.type?.startsWith('image/')) ? (
                        <img src={url} alt={name} className="w-full h-full object-cover" />
                    ) : (
                        <svg className="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    )}
                </div>
                <div className="flex-grow min-w-0">
                    <p className="text-xs font-semibold text-gray-700 dark:text-gray-300 truncate">{name}</p>
                    {!isUrl && <p className="text-[10px] text-gray-400 dark:text-gray-500">{(fileItem.size / 1024).toFixed(1)} KB</p>}
                </div>
            </div>
        );
    };

    if (isMultiple) {
        const currentFiles = Array.isArray(value) ? value : (value ? [value] : []);
        return (
            <div className="mt-1 space-y-3">
                <div 
                    className={`flex items-center justify-center w-full transition-all ${dragActive ? 'scale-[1.02]' : ''}`}
                    onDragEnter={handleDrag}
                    onDragLeave={handleDrag}
                    onDragOver={handleDrag}
                    onDrop={handleDrop}
                >
                    <label className={`flex flex-col items-center justify-center w-full h-24 border-2 ${dragActive ? 'border-indigo-500 dark:border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 border-solid' : 'border-gray-200 dark:border-gray-700 border-dashed bg-gray-50 dark:bg-gray-800'} rounded-2xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors`}>
                        <div className="flex flex-col items-center justify-center pt-4 pb-4">
                            <svg className={`w-6 h-6 mb-2 ${dragActive ? 'text-indigo-500 dark:text-indigo-400 animate-bounce' : 'text-gray-400 dark:text-gray-500'}`} aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                <path stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                            </svg>
                            <p className="text-xs text-gray-500 dark:text-gray-400 font-semibold">{dragActive ? 'Drop files here!' : 'Click or drop multiple files'}</p>
                        </div>
                        <input 
                            type="file" multiple
                            className="hidden" 
                            onChange={(e) => {
                                const newFilesList = Array.from(e.target.files || []);
                                onChange([...currentFiles, ...newFilesList]);
                            }}
                            accept={accept}
                        />
                    </label>
                </div>
                {currentFiles.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                        {currentFiles.map((fileItem: any, idx: number) => (
                            <div key={idx} className="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm transition-all hover:border-indigo-300 dark:hover:border-indigo-600">
                                {renderFilePreview(fileItem)}
                                <button 
                                    type="button" 
                                    onClick={() => {
                                        const newVal = [...currentFiles];
                                        newVal.splice(idx, 1);
                                        onChange(newVal);
                                    }} 
                                    className="absolute top-1/2 -translate-y-1/2 right-3 text-red-400 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 p-1 bg-red-50 dark:bg-red-900/40 hover:bg-red-100 dark:hover:bg-red-900/60 rounded-full transition-colors opacity-0 group-hover:opacity-100 shadow-sm"
                                    title="Remove file"
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
        );
    }

    return (
        <div className="mt-1">
            <div 
                className={`flex items-center justify-center w-full transition-all ${dragActive ? 'scale-[1.02]' : ''}`}
                onDragEnter={handleDrag}
                onDragLeave={handleDrag}
                onDragOver={handleDrag}
                onDrop={handleDrop}
            >
                <label className={`flex flex-col items-center justify-center w-full h-32 border-2 ${dragActive ? 'border-indigo-500 dark:border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 border-solid' : 'border-gray-200 dark:border-gray-700 border-dashed bg-gray-50 dark:bg-gray-800'} rounded-2xl cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-colors`}>
                    <div className="flex flex-col items-center justify-center pt-5 pb-6 text-gray-400 dark:text-gray-500">
                        <svg className={`w-8 h-8 mb-3 ${dragActive ? 'text-indigo-500 dark:text-indigo-400 animate-bounce' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <p className="text-xs font-semibold">
                            {dragActive ? 'Drop it here!' : 'Click or drag file here'}
                        </p>
                    </div>
                    <input 
                        type="file" 
                        className="hidden" 
                        onChange={e => e.target.files && onChange(e.target.files[0])}
                        accept={accept}
                    />
                </label>
            </div>
            {value && !Array.isArray(value) && (
                <div className="mt-3 relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm transition-all hover:border-indigo-300 dark:hover:border-indigo-600">
                    {renderFilePreview(value)}
                    <button 
                        type="button" 
                        onClick={() => onChange(null)} 
                        className="absolute top-1/2 -translate-y-1/2 right-3 text-red-400 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 p-1.5 bg-red-50 dark:bg-red-900/40 hover:bg-red-100 dark:hover:bg-red-900/60 rounded-full transition-colors opacity-0 group-hover:opacity-100"
                        title="Remove file"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2.5} stroke="currentColor" className="w-4 h-4">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            )}
        </div>
    );
}
