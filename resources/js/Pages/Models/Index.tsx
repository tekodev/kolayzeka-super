import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

interface ModelIndexProps {
    auth: any;
    aiModels: any[];
}

export default function Index({ aiModels, auth }: ModelIndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Explore AI Models
                </h2>
            }
        >
            <Head title="AI Models" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                        {aiModels.map((model) => (
                            <Link 
                                key={model.id} 
                                href={route('models.show', model.slug)}
                                className="group bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-2xl border border-gray-100 dark:border-gray-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
                            >
                                <div className="aspect-video w-full overflow-hidden bg-gray-100 relative">
                                    {model.image_url ? (
                                        <img 
                                            src={model.image_url.startsWith('http') ? model.image_url : `/storage/${model.image_url}`} 
                                            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                                            alt={model.name} 
                                        />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center text-gray-400">
                                            <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    )}
                                    <div className="absolute top-4 left-4 flex flex-wrap gap-2">
                                        {model.categories && model.categories.length > 0 ? (
                                            model.categories.map((category: any) => (
                                                <span 
                                                    key={category.id}
                                                    className="px-3 py-1 bg-white/90 backdrop-blur shadow-sm rounded-full text-xs font-bold text-indigo-600 uppercase tracking-wider"
                                                >
                                                    {category.name}
                                                </span>
                                            ))
                                        ) : (
                                            <span className="px-3 py-1 bg-white/90 backdrop-blur shadow-sm rounded-full text-xs font-bold text-gray-400 uppercase tracking-wider">
                                                Uncategorized
                                            </span>
                                        )}
                                    </div>
                                </div>
                                
                                <div className="p-6">
                                    <h3 className="text-xl font-bold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                        {model.name}
                                    </h3>
                                    <p className="mt-2 text-gray-600 dark:text-gray-300 line-clamp-2 text-sm leading-relaxed">
                                        {model.description}
                                    </p>
                                    
                                    <div className="mt-6 flex items-center justify-between">
                                         <div className="flex -space-x-2">
                                              {/* Provider Logo icon fallback */}
                                              <div className="w-8 h-8 rounded-full bg-indigo-50 dark:bg-indigo-900/30 border-2 border-white dark:border-gray-800 flex items-center justify-center">
                                                 <span className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">AI</span>
                                              </div>
                                         </div>
                                        <span className="text-indigo-600 font-semibold text-sm flex items-center gap-1 group-hover:gap-2 transition-all">
                                            Try it now
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>

                    {aiModels.length === 0 && (
                        <div className="text-center py-20 bg-white rounded-2xl shadow-sm border border-dashed border-gray-200">
                             <h3 className="text-lg font-medium text-gray-900">No models available</h3>
                             <p className="mt-1 text-gray-500">Check back later for new AI tools.</p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
