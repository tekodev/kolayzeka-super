import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';

interface VideoGenerationFormProps {
    generationId: number;
    routeName: string; // 'apps.luna-influencer.generate-video' or 'apps.ai-influencer.generate-video'
    originalPrompt?: string;
}

export default function VideoGenerationForm({ generationId, routeName, originalPrompt }: VideoGenerationFormProps) {
    const { data, setData, post, processing, errors } = useForm({
        generation_id: generationId,
        video_prompt: `A cinematic, photorealistic fashion presentation video.
A person wearing an elegant dress stands naturally in place with relaxed, balanced posture.
The subject remains mostly stationary, maintaining correct human anatomy and realistic proportions at all times.
They make a slow, subtle shift of weight and a very gentle partial turn to present the garment from multiple angles.
The movement is minimal, smooth, and controlled, with no rapid motion.
The dress fabric moves softly and naturally due to gravity and slight body motion, showing realistic texture and flow.
The subject faces the camera direction with a calm, confident expression.
Professional fashion lighting, realistic fabric detail, cinematic depth of field, high visual fidelity.
`,
        camera_movement: 'static',
        action: 'Slow partial turn, subtle weight shift, minimal motion, natural human movement, gentle fabric flow',
        duration: '8',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('VideoGenerationForm: Submitting video generation request...', data);
        console.log('VideoGenerationForm: Route is:', routeName);
        
        post(route(routeName), {
            preserveScroll: true,
            onSuccess: () => {
                // Success handled by flash message
            },
            onError: (errors) => {
                console.error('Video generation failed:', errors);
                // Errors are automatically set in form state
            }
        });
    };

    return (
        <div className="bg-gradient-to-br from-purple-50 to-indigo-50 rounded-lg p-6 border-2 border-purple-200">
            <h3 className="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                <svg className="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                Video Oluştur
            </h3>

            {/* Error Display */}
            {(errors as any).error && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <div className="flex items-start gap-2">
                        <svg className="w-5 h-5 text-red-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                        </svg>
                        <p className="text-sm text-red-800 font-medium">{(errors as any).error}</p>
                    </div>
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Video Prompt */}
                <div>
                    <InputLabel value="Video Prompt" />
                    <textarea
                        className="mt-1 block w-full border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm"
                        rows={4}
                        value={data.video_prompt}
                        onChange={e => setData('video_prompt', e.target.value)}
                        placeholder="Describe the video motion and action..."
                    />
                    <p className="text-xs text-gray-500 mt-1">
                        Kameranın hareketini, kişinin ne yaptığını ve istediğiniz atmosferi detaylı açıklayın.
                    </p>
                    <InputError message={errors.video_prompt} className="mt-2" />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Camera Movement */}
                    <div>
                        <InputLabel value="Kamera Hareketi" />
                        <select
                            className="mt-1 block w-full border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm"
                            value={data.camera_movement}
                            onChange={e => setData('camera_movement', e.target.value)}
                        >
                            <option value="static">Sabit (Static)</option>
                            <option value="slow pan">Yavaş Kaydırma (Slow Pan)</option>
                            <option value="dolly in">Yakınlaşma (Dolly In)</option>
                            <option value="dolly out">Uzaklaşma (Dolly Out)</option>
                            <option value="tracking shot">Takip Çekimi (Tracking)</option>
                            <option value="orbit">Çevreden Dönme (Orbit)</option>
                        </select>
                        <InputError message={errors.camera_movement} className="mt-2" />
                    </div>

                    {/* Action */}
                    <div>
                        <InputLabel value="Aksiyon" />
                        <input
                            type="text"
                            className="mt-1 block w-full border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm"
                            value={data.action}
                            onChange={e => setData('action', e.target.value)}
                            placeholder="e.g., gentle twirl, walking, posing"
                        />
                        <InputError message={errors.action} className="mt-2" />
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Duration */}
                    <div>
                        <InputLabel value="Süre" />
                        <select
                            className="mt-1 block w-full border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm"
                            value={data.duration}
                            onChange={e => setData('duration', e.target.value)}
                        >
                            <option value="4">4 saniye</option>
                            <option value="6">6 saniye</option>
                            <option value="8">8 saniye (Önerilen)</option>
                        </select>
                        <InputError message={errors.duration} className="mt-2" />
                    </div>
                </div>

                {/* Info Box */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex items-start gap-2">
                        <svg className="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                        </svg>
                        <div className="text-sm text-blue-800">
                            <p className="font-semibold mb-1">Video Oluşturma Süresi: 1-6 dakika</p>
                            <p>Video, oluşturduğunuz fotoğrafı ilk kare olarak kullanacak ve 3 referans görsel ile tutarlılığı sağlayacaktır:</p>
                            <ul className="list-disc list-inside mt-1 space-y-1">
                                <li>İlk Kare: Oluşturduğunuz fotoğraf</li>
                                <li>Kıyafet Referansı: Yüklediğiniz kıyafet görseli</li>
                                <li>Yüz Referansı: Model yüz görseli</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Submit Button */}
                <div className="flex justify-end">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Video Oluşturuluyor...' : '🎬 Video Oluştur'}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    );
}
