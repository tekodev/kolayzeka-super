import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import React, { useState } from 'react';

interface DocsProps {
    auth: any;
    aiModel: any;
    appUrl: string;
}

export default function Docs({ aiModel, auth, appUrl }: DocsProps) {
    const [copied, setCopied] = useState(false);

    const provider = aiModel.providers?.[0];
    const schema = provider?.schema;
    const inputSchema = schema?.input_schema || [];
    const outputType = aiModel.output_type || 'image';

    const apiUrl = `${appUrl}/api/models/${aiModel.slug}/generate`;

    // Generate example request body
    const generateExampleBody = () => {
        const body: Record<string, any> = {};
        inputSchema.forEach((field: any) => {
            body[field.key] = field.example || field.default || (field.type === 'number' ? 0 : 'string');
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

    const sections = [
        { id: 'api', title: 'API Reference', icon: '🚀' },
        { id: 'auth', title: 'Authentication', icon: '🔐' },
        { id: 'schema', title: 'Request Schema', icon: '📋' },
        { id: 'errors', title: 'Error Codes', icon: '⚠️' }
    ];

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href={route('models.show', aiModel.slug)} className="text-gray-400 hover:text-indigo-600 transition-colors">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {aiModel.name} API Guide
                    </h2>
                </div>
            }
        >
            <Head title={`${aiModel.name} - API Documentation`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
                        
                        {/* Sidebar Navigation (Sticky) */}
                        <div className="hidden lg:block">
                            <div className="sticky top-8 space-y-2">
                                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-6 px-4">Contents</p>
                                {sections.map((section) => (
                                    <a
                                        key={section.id}
                                        href={`#${section.id}`}
                                        className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold text-gray-500 hover:bg-white hover:text-indigo-600 transition-all hover:shadow-sm"
                                    >
                                        <span>{section.icon}</span>
                                        {section.title}
                                    </a>
                                ))}
                            </div>
                        </div>

                        {/* Main Content (Single Page) */}
                        <div className="lg:col-span-3 space-y-16">
                            
                            {/* API Overview Section */}
                            <section id="api" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                                <div className="flex items-center gap-4 mb-8">
                                    <div className="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-2xl">🚀</div>
                                    <h3 className="text-3xl font-black text-gray-900">API Reference</h3>
                                </div>
                                
                                <div className="space-y-12">
                                    <div>
                                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">Endpoint</h4>
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
                                    </div>

                                    <div>
                                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">cURL Implementation</h4>
                                        <div className="relative group">
                                            <pre className="bg-gray-900 text-green-400 p-6 rounded-2xl font-mono text-sm overflow-x-auto leading-relaxed border border-indigo-500/20 shadow-2xl">
                                                {curlExample}
                                            </pre>
                                            <button 
                                                onClick={() => copyToClipboard(curlExample)}
                                                className="absolute top-4 right-4 bg-white/10 hover:bg-white/20 p-2 rounded-xl text-white transition-all backdrop-blur"
                                            >
                                                {copied ? 'Copied!' : (
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                    </svg>
                                                )}
                                            </button>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="text-sm font-black text-gray-400 uppercase tracking-widest mb-4">Response Sample</h4>
                                        <div className="p-6 bg-indigo-50 rounded-2xl border border-indigo-100">
                                            <pre className="text-sm text-gray-800 font-mono">
{`{
  "status": "completed",
  "output": {
    "result": "${outputType === 'image' ? 'https://storage.kolayzeka.com/res.jpg' : outputType === 'video' ? 'https://storage.kolayzeka.com/res.mp4' : 'Content string'}",
    "thumbnail_url": "..."
  },
  "metrics": { "duration": 4.52 }
}`}
                                            </pre>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {/* Authentication Section */}
                            <section id="auth" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                                <div className="flex items-center gap-4 mb-8">
                                    <div className="w-12 h-12 bg-blue-50 rounded-2xl flex items-center justify-center text-2xl">🔐</div>
                                    <h3 className="text-3xl font-black text-gray-900">Authentication</h3>
                                </div>
                                <div className="space-y-6">
                                    <p className="text-gray-600 leading-relaxed">
                                        All API requests must be authenticated using the <code className="bg-gray-100 px-2 py-0.5 rounded">Bearer</code> scheme in the Authorization header.
                                    </p>
                                    <div className="bg-blue-900/5 p-6 rounded-2xl border border-blue-100">
                                        <code className="text-blue-700 font-bold block mb-2">Authorization: Bearer YOUR_TOKEN</code>
                                    </div>
                                    <p className="text-sm text-gray-500 italic">
                                        You can generate and manage your API tokens in your <Link href={route('profile.edit')} className="text-indigo-600 font-bold hover:underline">Profile Settings</Link>.
                                    </p>
                                </div>
                            </section>

                            {/* Schema Section */}
                            <section id="schema" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                                <div className="flex items-center gap-4 mb-8">
                                    <div className="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-2xl">📋</div>
                                    <h3 className="text-3xl font-black text-gray-900">Request Schema</h3>
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="border-b border-gray-100">
                                                <th className="pb-4 font-black uppercase tracking-[0.1em] text-[10px] text-gray-400">Parameter</th>
                                                <th className="pb-4 font-black uppercase tracking-[0.1em] text-[10px] text-gray-400">Type</th>
                                                <th className="pb-4 font-black uppercase tracking-[0.1em] text-[10px] text-gray-400">Req.</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-50">
                                            {inputSchema.map((item: any) => (
                                                <tr key={item.key}>
                                                    <td className="py-4 font-mono text-indigo-600 font-bold text-sm tracking-tight">{item.key}</td>
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

                            {/* Errors Section */}
                            <section id="errors" className="bg-white p-8 lg:p-12 shadow-xl sm:rounded-3xl border border-gray-100 scroll-mt-8">
                                <div className="flex items-center gap-4 mb-8">
                                    <div className="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center text-2xl">⚠️</div>
                                    <h3 className="text-3xl font-black text-gray-900">Error Handling</h3>
                                </div>
                                <div className="grid grid-cols-1 gap-4">
                                    {[
                                        { code: 401, title: 'Unauthorized', msg: 'Missing/Invalid token.' },
                                        { code: 402, title: 'Credits Expired', msg: 'Insufficient balance.' },
                                        { code: 422, title: 'Validation Error', msg: 'Invalid JSON/parameters.' },
                                        { code: 500, title: 'Provider Failed', msg: 'AI engine did not respond.' }
                                    ].map((error) => (
                                        <div key={error.code} className="p-4 border border-gray-100 rounded-2xl flex items-center justify-between hover:bg-gray-50 transition-all">
                                            <div className="flex items-center gap-4">
                                                <span className="text-red-600 font-black">{error.code}</span>
                                                <p className="text-gray-900 font-bold text-sm">{error.title}</p>
                                            </div>
                                            <p className="text-gray-400 text-xs italic">{error.msg}</p>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
