import React, { useEffect, useState } from 'react';

interface GenerationProgressProps {
    processing: boolean;
    progressPercentage?: number; // 0-100 real progress (e.g. upload)
}

export default function GenerationProgress({ processing, progressPercentage = 0 }: GenerationProgressProps) {
    const [visualProgress, setVisualProgress] = useState(0);
    const [statusText, setStatusText] = useState('Initializing...');

    useEffect(() => {
        if (!processing) {
            setVisualProgress(0);
            return;
        }

        // Logic A: Real Upload Progress (0 < p < 100)
        if (progressPercentage > 0 && progressPercentage < 100) {
            setVisualProgress(progressPercentage);
            setStatusText(`UPLOADING ASSETS... ${progressPercentage}%`);
        } 
        // Logic B: Simulated "Thinking" Phase (Upload done or not needed)
        else {
            const interval = setInterval(() => {
                setVisualProgress((prev) => {
                    // Fast initially (0-30), steady (30-80), slow (80-99)
                    if (prev >= 99) return 99;
                    
                    const increment = prev < 30 ? 2 : prev < 80 ? 0.5 : 0.1;
                    return Math.min(prev + increment, 99);
                });
            }, 100);

            return () => clearInterval(interval);
        }
    }, [processing, progressPercentage]);

    // Update Text based on Visual Progress
    useEffect(() => {
        if (progressPercentage > 0 && progressPercentage < 100) return; // Keep upload text
        
        if (visualProgress < 10) setStatusText('INITIALIZING REQUEST...');
        else if (visualProgress < 40) setStatusText('AI IS GENERATING...');
        else if (visualProgress < 75) setStatusText('ENHANCING DETAILS...');
        else setStatusText('FINALIZING MAGIC...');
    }, [visualProgress, progressPercentage]);

    if (!processing) return null;

    return (
        <div className="w-full bg-white rounded-2xl border border-indigo-50 p-6 text-center animate-fade-in relative overflow-hidden">
            {/* Background Decoration */}
            <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
            
            <div className="py-4">
                {/* Custom Spinner / Logo Animation */}
                <div className="mb-6 relative w-20 h-20 mx-auto">
                    <div className="absolute inset-0 border-4 border-indigo-50 rounded-full"></div>
                    <div 
                        className="absolute inset-0 border-4 border-indigo-600 rounded-full border-t-transparent animate-spin"
                        style={{ animationDuration: '1s' }}
                    ></div>
                    <div className="absolute inset-0 flex items-center justify-center text-lg font-bold text-gray-800">
                        {Math.round(visualProgress)}%
                    </div>
                </div>

                <h2 className="text-xl font-bold mb-2 text-gray-900 tracking-tight">Oluşturuluyor...</h2>
                <p className="text-indigo-600 text-xs font-semibold uppercase tracking-widest mb-6 animate-pulse">
                    {statusText}
                </p>

                {/* Progress Bar Container */}
                <div className="h-2 bg-gray-100 rounded-full overflow-hidden shadow-inner mx-2">
                    <div 
                        className="h-full bg-indigo-600 transition-all duration-300 ease-out shadow-[0_0_10px_rgba(79,70,229,0.4)]"
                        style={{ width: `${visualProgress}%` }}
                    ></div>
                </div>

                <p className="mt-4 text-[10px] text-gray-400">
                    İşlem yaklaşık 30-40 saniye sürebilir.<br/>Lütfen sayfayı kapatmayın.
                </p>
            </div>
        </div>
    );
}
