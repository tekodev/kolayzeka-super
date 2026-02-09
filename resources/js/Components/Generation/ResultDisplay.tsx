import React from 'react';

interface ResultDisplayProps {
    generation: any;
    error?: string;
    onCreateVideo?: () => void; // Callback to show video form
}

export default function ResultDisplay({ generation, error, onCreateVideo }: ResultDisplayProps) {
    const [isHovering, setIsHovering] = React.useState(false);
    const [mousePosition, setMousePosition] = React.useState({ x: 0, y: 0 });

    const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
        const { left, top, width, height } = e.currentTarget.getBoundingClientRect();
        const x = ((e.clientX - left) / width) * 100;
        const y = ((e.clientY - top) / height) * 100;
        setMousePosition({ x, y });
    };

    const handleDownload = () => {
        console.log('Download Debug:', generation);
        if (!generation.id) {
            console.error('Download aborted: No ID found in generation object');
            return;
        }
        // Manual URL construction to avoid potential Ziggy issues
        const url = `/apps/download/${generation.id}`;
        console.log('Navigating to:', url);
        window.location.href = url;
    };

    if (error) {
        return (
            <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl flex items-center gap-3">
                <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span className="font-medium">{error}</span>
            </div>
        );
    }

    if (!generation) return null;

    return (
        <div className="bg-white shadow-xl sm:rounded-2xl border border-indigo-100 overflow-hidden animate-fade-in-up">
            <div className="bg-indigo-600 px-8 py-4 flex justify-between items-center">
                <h3 className="text-white font-bold flex items-center gap-2 uppercase tracking-widest text-sm">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z" />
                    </svg>
                    Generated Magic
                </h3>
                <span className="px-3 py-1 bg-white/20 backdrop-blur rounded-full text-white text-[10px] uppercase font-black">
                    {generation.status}
                </span>
            </div>
            <div className="p-8">
                <div className="flex justify-center bg-gray-50 rounded-2xl p-4 min-h-[300px] items-center border border-gray-100 overflow-hidden relative group">
                    {generation.output_data?.result ? (
                        <div 
                            className="relative overflow-hidden rounded-xl shadow-2xl cursor-zoom-in w-full max-w-lg mx-auto"
                            onMouseMove={handleMouseMove}
                            onMouseEnter={() => setIsHovering(true)}
                            onMouseLeave={() => setIsHovering(false)}
                            onClick={() => window.open(generation.output_data.result, '_blank')}
                        >
                            {/* Check if result is video or image */}
                            {typeof generation.output_data.result === 'string' && generation.output_data.result.match(/\.(mp4|webm)(\?|$)/) ? (
                                <video 
                                    src={generation.output_data.result} 
                                    className="w-full h-auto rounded-xl"
                                    controls
                                    autoPlay
                                    loop
                                    muted
                                    playsInline
                                />
                            ) : (
                                <img 
                                    src={typeof generation.output_data.result === 'string' ? generation.output_data.result : ''} 
                                    className="w-full h-auto object-cover transition-transform duration-200 ease-out"
                                    style={{
                                        transformOrigin: `${mousePosition.x}% ${mousePosition.y}%`,
                                        transform: isHovering ? 'scale(2.5)' : 'scale(1)',
                                    }}
                                    alt="Generated Outcome"
                                />
                            )}
                        </div>
                    ) : generation.status === 'processing' ? (
                        <div className="text-center space-y-4">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600 mx-auto"></div>
                            <p className="text-indigo-600 font-medium animate-pulse">
                                Sihir hazırlanıyor... Lütfen bekleyin.
                            </p>
                            <p className="text-xs text-gray-400">Video oluşturma 1-6 dakika sürebilir.</p>
                        </div>
                    ) : generation.status === 'failed' ? (
                        <div className="text-center space-y-4 max-w-lg mx-auto p-6 bg-red-50 rounded-2xl border border-red-100">
                             <div className="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                                <svg className="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <h3 className="text-lg leading-6 font-medium text-red-900">İşlem Tamamlanamadı</h3>
                            <div className="mt-2">
                                <p className="text-sm text-red-700">
                                    {generation.error_message || 'Bilinmeyen bir hata oluştu.'}
                                </p>
                            </div>
                            <div className="mt-4">
                                <p className="text-xs text-red-500 italic">
                                    Hata durumunda harcanan krediniz iade edilmiştir.
                                </p>
                            </div>
                            {/* Debug info hidden by default */}
                            <details className="mt-4 text-left">
                                <summary className="text-xs text-gray-400 cursor-pointer hover:text-gray-600">Teknik Detaylar</summary>
                                <pre className="text-[10px] bg-gray-900 text-green-400 p-4 rounded-lg overflow-auto mt-2 max-h-40">
                                    {JSON.stringify(generation.output_data, null, 2)}
                                </pre>
                            </details>
                        </div>
                    ) : (
                        <div className="text-center space-y-2">
                            <p className="text-gray-400 font-medium">Data not visual.</p>
                        </div>
                    )}
                </div>
                
                {/* Action Buttons */}
                {generation.output_data?.result && (
                    <div className="mt-6 flex flex-wrap justify-center gap-4">
                        <button
                            onClick={handleDownload}
                            className="inline-flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 font-bold rounded-xl shadow-sm transition-all duration-200"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            İndir
                        </button>

                        {onCreateVideo && (
                            <button
                                onClick={onCreateVideo}
                                className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-105"
                            >
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Video Oluştur
                            </button>
                        )}
                    </div>
                )}
                
                <div className="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="p-4 rounded-xl bg-gray-50 border border-gray-100">
                        <p className="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Maliyet</p>
                        <p className="text-lg font-bold text-indigo-700">{generation.user_credit_cost} Kredi</p>
                    </div>
                    <div className="p-4 rounded-xl bg-gray-50 border border-gray-100">
                        <p className="text-[10px] text-gray-400 uppercase font-black tracking-widest mb-1">Oluşturulma Tarihi</p>
                        <p className="text-sm font-bold text-gray-700">
                            {new Date(generation.created_at).toLocaleString()}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
