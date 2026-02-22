import React, { useState } from 'react';
import ResultDisplay from '@/Components/Generation/ResultDisplay';

interface Generation {
    id: number;
    status: string;
    created_at: string;
    meta_data: any;
    ai_model: {
        name: string;
        image_url: string;
    };
    output_data: {
        result?: string;
        thumbnail?: string;
        [key: string]: any;
    }; 
    thumbnail_url?: string; 
    user_credit_cost: number;
}

interface HistoryItemProps {
    generation: Generation;
}

export default function HistoryItem({ generation }: HistoryItemProps) {
    const [expanded, setExpanded] = useState(false);

    return (
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-100 p-6 transition-all duration-300 hover:shadow-md">
            <div className="flex justify-between items-start mb-4">
                <div className="flex items-center gap-3">
                    {/* Model Icon / Thumbnail */}
                    {generation.ai_model?.image_url ? (
                        <img 
                            src={`/storage/${generation.ai_model.image_url}`} 
                            className="w-10 h-10 rounded-full object-cover border border-gray-200" 
                            alt={generation.ai_model.name} 
                        />
                    ) : (
                        <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold">
                            {generation.ai_model?.name?.substring(0, 2) || 'AI'}
                        </div>
                    )}
                    
                    <div>
                        <h4 className="font-bold text-gray-800 text-sm md:text-base">
                            {generation.ai_model?.name || 'Unknown Model'}
                        </h4>
                        <p className="text-xs text-gray-500">
                            {new Date(generation.created_at).toLocaleString()}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-2">
                    <span
                        className={`px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider
                        ${generation.status === 'completed' ? 'bg-green-50 text-green-700 border border-green-100' : 
                          generation.status === 'failed' ? 'bg-red-50 text-red-700 border border-red-100' : 
                          'bg-yellow-50 text-yellow-700 border border-yellow-100'}`}
                    >
                        {generation.status}
                    </span>

                    {/* Thumbnail Logic */}
                    {generation.status === 'completed' && (generation.thumbnail_url || generation.output_data?.thumbnail) ? (
                        <div className="relative group cursor-pointer" onClick={() => {
                            const url = Array.isArray(generation.output_data.result) ? generation.output_data.result[0] : (generation.output_data.result as string)
                            window.open(url, '_blank')
                        }}>
                            <img 
                                src={generation.thumbnail_url || generation.output_data?.thumbnail} 
                                alt="Generated Thumbnail" 
                                className="w-24 h-24 object-cover rounded-lg border border-gray-200 shadow-sm transition-transform hover:scale-105"
                            />
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors rounded-lg flex items-center justify-center">
                                <svg className="w-6 h-6 text-white opacity-0 group-hover:opacity-100 drop-shadow-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                </svg>
                            </div>
                        </div>
                    ) : (
                        <button
                            onClick={() => setExpanded(!expanded)}
                            className="text-indigo-600 text-xs font-semibold hover:text-indigo-800 transition-colors flex items-center gap-1"
                        >
                            {expanded ? 'Hide Result' : 'View Result'}
                            <svg 
                                className={`w-4 h-4 transition-transform duration-200 ${expanded ? 'rotate-180' : ''}`} 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    )}
                </div>
            </div>

            {/* Expanded Content (Only for non-thumbnail items or detailed view if needed) */}
            {expanded && !(generation.thumbnail_url || generation.output_data?.thumbnail) && (
                <div className="mt-4 animate-fade-in-down">
                    <ResultDisplay generation={generation} />
                </div>
            )}
        </div>
    );
}
