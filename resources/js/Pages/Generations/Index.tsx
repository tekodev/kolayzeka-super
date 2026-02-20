import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import HistoryItem from '@/Components/Generation/HistoryItem';

interface Generation {
    id: number;
    status: string;
    created_at: string;
    meta_data: any;
    ai_model: {
        name: string;
        image_url: string;
    };
    output_data: any;
    user_credit_cost: number;
}

interface Props {
    auth: any;
    generations: {
        data: Generation[];
        links: any[];
    };
}

export default function GenerationsIndex({ auth, generations }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    My History
                </h2>
            }
        >
            <Head title="Generation History" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    
                    {generations.data.length === 0 ? (
                         <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-12 text-center">
                            <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100">No generations yet</h3>
                            <p className="mt-2 text-gray-500 dark:text-gray-400">Create your first AI masterpiece today!</p>
                            <Link 
                                href={route('dashboard')} 
                                className="mt-6 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition"
                            >
                                Go to Dashboard
                            </Link>
                         </div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6">
                            {generations.data.map((generation) => (
                                <HistoryItem key={generation.id} generation={generation} />
                            ))}
                        </div>
                    )}

                    {/* Pagination */}
                    {generations.links.length > 3 && (
                        <div className="mt-6 flex justify-center">
                            <div className="flex flex-wrap gap-1">
                                {generations.links.map((link, key) => (
                                    link.url ? (
                                        <Link
                                            key={key}
                                            href={link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={key}
                                            className="px-3 py-1 text-sm text-gray-400"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                ))}
                            </div>
                        </div>
                    )}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
