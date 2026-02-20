import React, { useState } from 'react';
import { Link } from '@inertiajs/react';

interface AppDocsProps {
    app: {
        name: string;
        slug: string;
        description: string;
        steps: Array<{
            name: string;
            ai_model: {
                name: string;
            };
        }>;
    };
    fields: Array<{
        key: string;
        label: string;
        type: string;
        description?: string;
        required?: boolean;
        defaultValue?: any;
    }>;
}

export default function AppDocs({ app, fields }: AppDocsProps) {
    const [copied, setCopied] = useState(false);
    const appUrl = window.location.origin;
    const apiUrl = `${appUrl}/api/apps/${app.slug}/execute`;

    // Generate example request body
    const generateExampleBody = () => {
        const body: Record<string, any> = {};
        fields.forEach((field) => {
            body[field.key] = field.defaultValue || (field.type === 'number' || field.type === 'range' ? 0 : 'string');
        });
        return JSON.stringify(body, null, 2);
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const curlExample = `curl -X POST ${apiUrl} \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '${generateExampleBody()}'`;

    return (
        <div className="space-y-16 animate-fade-in-up pb-20">
            {/* 1. API Endpoint Section */}
            <section id="api" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                <div className="flex items-center gap-4 mb-8">
                    <div className="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl">🚀</div>
                    <h3 className="text-3xl font-black text-gray-900">API Reference</h3>
                </div>
                
                <div className="space-y-12">
                    <div>
                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">Execution Endpoint</h4>
                        <div className="flex items-center gap-4 bg-gray-900 p-4 rounded-xl text-white font-mono text-sm group relative">
                            <span className="bg-green-500 text-black px-3 py-1 rounded-md font-black text-[10px] uppercase">POST</span>
                            <code className="break-all">{apiUrl}</code>
                            <button 
                                onClick={() => copyToClipboard(apiUrl)}
                                className="ml-auto p-2 hover:bg-white/10 rounded-lg transition-colors"
                            >
                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </button>
                        </div>
                        <p className="mt-4 text-sm text-gray-500 italic">
                            Starts the multi-step execution pipeline for <strong>{app.name}</strong>.
                        </p>
                    </div>

                    <div>
                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">cURL Implementation</h4>
                        <div className="relative group">
                            <pre className="bg-gray-900 text-green-400 p-6 rounded-2xl font-mono text-sm overflow-x-auto leading-relaxed border border-indigo-500/20 shadow-2xl">
                                {curlExample}
                            </pre>
                            <button 
                                onClick={() => copyToClipboard(curlExample)}
                                className="absolute top-4 right-4 bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-xl text-white text-[10px] font-black transition-all backdrop-blur"
                            >
                                {copied ? 'COPIED!' : 'COPY'}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {/* 2. Authentication Section */}
            <section id="auth" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                <div className="flex items-center gap-4 mb-8">
                    <div className="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">🔐</div>
                    <h3 className="text-3xl font-black text-gray-900">Authentication</h3>
                </div>
                <div className="space-y-6">
                    <p className="text-gray-600 leading-relaxed">
                        Authorize your requests using the <code className="bg-gray-100 px-2 py-0.5 rounded">Bearer</code> token scheme.
                    </p>
                    <div className="bg-blue-900/5 p-6 rounded-2xl border border-blue-100 inline-block">
                        <code className="text-blue-700 font-bold">Authorization: Bearer YOUR_TOKEN</code>
                    </div>
                    <p className="text-sm text-gray-500 italic">
                        Generate tokens in your <Link href={route('profile.edit')} className="text-indigo-600 font-bold hover:underline">Profile Settings</Link>.
                    </p>
                </div>
            </section>

            {/* 3. Request Schema Section */}
            <section id="schema" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                <div className="flex items-center gap-4 mb-8">
                    <div className="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-2xl">📋</div>
                    <h3 className="text-3xl font-black text-gray-900">Request Body</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-left border-collapse">
                        <thead>
                            <tr className="border-b border-gray-100">
                                <th className="pb-4 font-black uppercase tracking-[0.1em] text-[10px] text-gray-400">Parameter</th>
                                <th className="pb-4 font-black uppercase tracking-[0.1em] text-[10px] text-gray-400">Type</th>
                                <th className="pb-4 font-black uppercase tracking-[0.1em] text-[10px] text-gray-400">Required</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {fields.map((item) => (
                                <tr key={item.key}>
                                    <td className="py-4">
                                        <div className="font-mono text-indigo-600 font-bold text-sm tracking-tight">{item.key}</div>
                                        {item.description && <div className="text-[10px] text-gray-400 mt-0.5">{item.description}</div>}
                                    </td>
                                    <td className="py-4">
                                        <span className="bg-gray-100 px-2 py-0.5 rounded text-[10px] uppercase font-black text-gray-500">{item.type || 'text'}</span>
                                    </td>
                                    <td className="py-4">
                                        {item.required ? <span className="text-red-500 font-black text-[10px]">YES</span> : <span className="text-gray-300 font-black text-[10px]">NO</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            {/* 4. Response Section */}
            <section id="response" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                <div className="flex items-center gap-4 mb-8">
                    <div className="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center text-2xl">📡</div>
                    <h3 className="text-3xl font-black text-gray-900">Response Format</h3>
                </div>
                
                <div className="space-y-8">
                    <div>
                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">Success (202 Accepted)</h4>
                        <div className="p-6 bg-indigo-50 rounded-2xl border border-indigo-100">
                            <pre className="text-xs text-indigo-900 font-mono">
{`{
  "status": "success",
  "message": "Application execution started.",
  "execution": {
    "id": 1284,
    "status": "processing",
    "current_step": 1,
    "inputs": { ... }
  }
}`}
                            </pre>
                        </div>
                        <p className="mt-4 text-xs text-gray-400 leading-relaxed italic">
                            * Applications are executed asynchronously. Use the status endpoint to poll for completion.
                        </p>
                    </div>

                    <div className="pt-8 border-t border-gray-100">
                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">Status Polling Endpoint</h4>
                        <div className="bg-gray-900 p-4 rounded-xl text-white font-mono text-sm leading-relaxed mb-4">
                            <span className="bg-blue-500 text-black px-2 py-0.5 rounded text-[10px] font-black mr-4">GET</span>
                            {appUrl}/api/apps/execution/{"{execution_id}"}
                        </div>
                         <div className="p-6 bg-gray-50 rounded-2xl border border-gray-100">
                            <pre className="text-xs text-gray-600 font-mono">
{`{
  "id": 1284,
  "status": "completed",
  "current_step": 3,
  "history": {
    "1": { "result": "https://..." },
    "2": { "result": "..." }
  }
}`}
                            </pre>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
