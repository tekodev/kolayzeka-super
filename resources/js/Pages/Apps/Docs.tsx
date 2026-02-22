import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import React from 'react';
import AppDocs from '@/Components/Generation/AppDocs';

interface AppDocsPageProps {
    auth: any;
    app: any;
    fields: Array<{
        key: string;
        label: string;
        type: string;
        description?: string;
        required?: boolean;
        defaultValue?: any;
    }>;
}

export default function Docs({ auth, app, fields }: AppDocsPageProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center gap-4">
                    <Link href={route('apps.show', app.slug)} className="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {app.name} — API Dokümantasyonu
                    </h2>
                </div>
            }
        >
            <Head title={`${app.name} - API Dokümantasyonu`} />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">

                        {/* Sidebar Navigation */}
                        <div className="hidden lg:block">
                            <div className="sticky top-8 space-y-2">
                                <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-6 px-4">İçerik</p>
                                {[
                                    { id: 'api', title: 'API Referansı', icon: '🚀' },
                                    { id: 'auth', title: 'Kimlik Doğrulama', icon: '🔐' },
                                    { id: 'schema', title: 'İstek Şeması', icon: '📋' },
                                    { id: 'response', title: 'Yanıt Formatı', icon: '📡' },
                                ].map((section) => (
                                    <a
                                        key={section.id}
                                        href={`#${section.id}`}
                                        className="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold text-gray-500 hover:bg-white dark:hover:bg-gray-800 hover:text-indigo-600 dark:hover:text-indigo-400 transition-all hover:shadow-sm"
                                    >
                                        <span>{section.icon}</span>
                                        {section.title}
                                    </a>
                                ))}
                            </div>
                        </div>

                        {/* Main Content */}
                        <div className="lg:col-span-3">
                            <AppDocs app={app} fields={fields} />
                        </div>

                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
