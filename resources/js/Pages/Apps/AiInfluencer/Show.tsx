import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import ResultDisplay from '@/Components/Generation/ResultDisplay';

import GenerationProgress from '@/Components/Generation/GenerationProgress';
import VideoGenerationForm from '@/Components/Generation/VideoGenerationForm';

// Options Constants
const ASPECT_RATIOS = ['1:1', '2:3', '3:2', '3:4', '4:3', '4:5', '5:4', '9:16', '16:9', '21:9'];
const FRAMING_TYPES = ['full body', 'three-quarter', 'waist-up', 'portrait', 'head-to-toe'];
const CAMERA_DISTANCES = ['close-up', 'medium distance', 'full-body distance'];
const LENS_TYPES = ['24mm', '35mm', '50mm prime lens', '85mm portrait lens', '105mm', '135mm', '70–200mm'];

export default function AiInfluencerShow({ auth }: { auth: any }) {
    const { props } = usePage();
    const flash = props.flash as any;
    
    // Form State
    const { data, setData, post, processing, errors, reset, progress } = useForm({
        aspect_ratio: '4:5', // Instagram default
        framing_type: 'waist-up',
        camera_distance: 'medium distance',
        frame_coverage: 70,
        lens_type: '85mm portrait lens',
        location_description: 'Her own bedroom, styled like a realistic vlog setup; clean, modern, lived-in, and natural. The environment feels personal, authentic, and high-quality, suitable for a lifestyle vlog.',
        activity_style: 'Standing naturally as if recording a vlog in her bedroom, but not holding a phone or camera',
        pose_style: 'Model-like, confident, balanced, and intentional, with natural posture and clean lines',
        gaze_direction: 'Directly at the camera (the viewer), with confident, natural eye contact',
        image_resolution: '1K',
        identity_reference_images: [] as File[],
        clothing_reference_images: [] as File[],
    });

    const [lensLocked, setLensLocked] = useState(true);
    const [distanceLocked, setDistanceLocked] = useState(true);
    const [aspectRatioLocked, setAspectRatioLocked] = useState(true);
    const [showVideoForm, setShowVideoForm] = useState(false);
    const resultsRef = useRef<HTMLDivElement>(null);

    // Poll for video generation completion
    useEffect(() => {
        const generation = (props as any).generation || (props as any).latest_generation;
        
        if (generation && generation.status === 'processing') {
            const interval = setInterval(() => {
                console.log('Polling for generation status...', generation.id);
                import('@inertiajs/react').then(({ router }) => {
                     // @ts-ignore
                     router.reload({ 
                        only: ['generation'],
                        // @ts-ignore
                        preserveScroll: true,
                        preserveState: true 
                     });
                });
            }, 5000);
            return () => clearInterval(interval);
        }
    }, [(props as any).generation?.status]);

    // Helper: Get recommended aspect ratios for a framing type
    const getRecommendedAspectRatios = (framingType: string): string[] => {
        switch (framingType) {
            case 'head-to-toe':
            case 'full body':
                return ['9:16', '4:5', '2:3'];
            case 'portrait':
                return ['1:1', '4:5', '3:4'];
            case 'waist-up':
                return ['4:5', '3:4', '1:1'];
            case 'three-quarter':
                return ['4:5', '9:16', '3:4'];
            default:
                return ['4:5'];
        }
    };

    // Auto-select Aspect Ratio based on framing
    useEffect(() => {
        if (!aspectRatioLocked) return;

        const recommended = getRecommendedAspectRatios(data.framing_type);
        if (recommended.length > 0) {
            setData('aspect_ratio', recommended[0]);
        }
    }, [data.framing_type, aspectRatioLocked]);

    // Auto-calculate Camera Distance
    useEffect(() => {
        if (!distanceLocked) return;

        let suggestedDistance = 'medium distance';
        const { framing_type } = data;

        if (framing_type === 'head-to-toe' || framing_type === 'full body') {
            suggestedDistance = 'full-body distance';
            setData(data => ({ ...data, camera_distance: suggestedDistance, frame_coverage: 70 }));
        } else if (framing_type === 'portrait') {
            suggestedDistance = 'close-up';
            setData(data => ({ ...data, camera_distance: suggestedDistance, frame_coverage: 85 }));
        } else {
            // three-quarter, waist-up
            suggestedDistance = 'medium distance';
            setData(data => ({ ...data, camera_distance: suggestedDistance, frame_coverage: 70 }));
        }
    }, [data.framing_type, distanceLocked]);

    // Auto-calculate Lens
    useEffect(() => {
        if (!lensLocked) return;

        let suggestedLens = '50mm prime lens';
        const { framing_type, camera_distance } = data;

        // Logic based on user table
        // Full body + Medium/Far -> 50mm
        // Knee-up (Three-quarter) + Medium -> 50-85mm -> 50mm
        // Waist-up + Medium/Close -> 85mm
        // Chest-up (High-waist/Portrait) + Close -> 85mm
        // Head-to-toe approx Full Body -> 50mm

        if (framing_type === 'full body' || framing_type === 'head-to-toe') {
            suggestedLens = '50mm prime lens';
        } else if (framing_type === 'three-quarter') {
            suggestedLens = '50mm prime lens'; 
        } else if (framing_type === 'waist-up') {
            suggestedLens = '85mm portrait lens';
        } else if (framing_type === 'portrait') {
             // Portrait can be close or medium. Default logic:
             if (camera_distance === 'close-up') {
                 suggestedLens = '85mm portrait lens'; // or 105mm? Standard portrait is 85mm
             } else {
                 suggestedLens = '85mm portrait lens';
             }
        }

        // Override based on strong distance signals if needed, 
        // but framing usually dictates lens choice for compression.
        // User rule: "Sadece yüz (Close) -> 135mm", "Göğüs üstü (Close) -> 105mm"
        if (camera_distance === 'close-up') {
            if (framing_type === 'portrait') suggestedLens = '85mm portrait lens'; 
            // If we had a specific "Headshot" framing, we'd use 135mm.
        }

        setData('lens_type', suggestedLens);
    }, [data.framing_type, data.camera_distance, lensLocked]);

    // Scroll to results
    useEffect(() => {
        if (flash.generation_result && resultsRef.current) {
            resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, [flash.generation_result]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('Submitting AI Influencer Generation Request', data);
        post(route('apps.ai-influencer.generate'), {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                console.error('AI Influencer Generation Failed', errors);
            }
        });
    };

    const handleFileChange = (key: 'identity_reference_images' | 'clothing_reference_images', files: FileList | null) => {
        if (files) {
            setData(key, Array.from(files));
        }
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">AI Influencer Generator</h2>}
        >
            <Head title="AI Influencer" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Form Section */}
                        <div className="lg:col-span-2 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                           <form onSubmit={handleSubmit} className="space-y-6">
                                {/* 1. Composition */}
                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium text-gray-900 border-b pb-2">1. Kompozisyon & Çerçeveleme</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <div className="flex justify-between items-center">
                                                <InputLabel value="En-Boy Oranı" />
                                                <button 
                                                    type="button" 
                                                    onClick={() => setAspectRatioLocked(!aspectRatioLocked)}
                                                    className="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                                >
                                                    {aspectRatioLocked ? 'Manuel' : 'Otomatik'}
                                                </button>
                                            </div>
                                            <select 
                                                className={`mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm ${aspectRatioLocked ? 'bg-gray-100 cursor-not-allowed text-gray-500' : ''}`}
                                                value={data.aspect_ratio}
                                                onChange={e => setData('aspect_ratio', e.target.value)}
                                                disabled={aspectRatioLocked}
                                            >
                                                {(() => {
                                                    const recommended = getRecommendedAspectRatios(data.framing_type);
                                                    const others = ASPECT_RATIOS.filter(r => !recommended.includes(r));
                                                    return (
                                                        <>
                                                            {recommended.map(r => (
                                                                <option key={r} value={r}>✓ {r} (Önerilen)</option>
                                                            ))}
                                                            {others.map(r => (
                                                                <option key={r} value={r}>{r}</option>
                                                            ))}
                                                        </>
                                                    );
                                                })()}
                                            </select>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {aspectRatioLocked 
                                                    ? `Çerçeveleme için önerilen: ${getRecommendedAspectRatios(data.framing_type).join(', ')}`
                                                    : 'Manuel seçim aktif'}
                                            </p>
                                            <InputError message={errors.aspect_ratio} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Çerçeveleme Tipi" />
                                            <select 
                                                className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                                value={data.framing_type}
                                                onChange={e => setData('framing_type', e.target.value)}
                                            >
                                                {FRAMING_TYPES.map(t => <option key={t} value={t}>{t}</option>)}
                                            </select>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {{
                                                    'full body': 'Tam Boy',
                                                    'three-quarter': 'Diz Üstü (3/4)',
                                                    'waist-up': 'Bel Üstü',
                                                    'portrait': 'Portre (Yüz)',
                                                    'head-to-toe': 'Baştan Ayağa'
                                                }[data.framing_type] || ''}
                                            </p>
                                            <InputError message={errors.framing_type} className="mt-2" />
                                        </div>
                                        <div>
                                            <div className="flex justify-between items-center">
                                                <InputLabel value="Kamera Uzaklığı" />
                                                <button 
                                                    type="button" 
                                                    onClick={() => setDistanceLocked(!distanceLocked)}
                                                    className="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                                >
                                                    {distanceLocked ? 'Manuel' : 'Otomatik'}
                                                </button>
                                            </div>
                                             <select 
                                                className={`mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm ${distanceLocked ? 'bg-gray-100 cursor-not-allowed text-gray-500' : ''}`}
                                                value={data.camera_distance}
                                                onChange={e => setData('camera_distance', e.target.value)}
                                                disabled={distanceLocked}
                                             >
                                                 {CAMERA_DISTANCES.map(d => <option key={d} value={d}>{d}</option>)}
                                             </select>
                                             <p className="text-xs text-gray-500 mt-1">
                                                {distanceLocked 
                                                    ? 'Çerçeveleme tipine göre otomatik seçildi.' 
                                                    : (
                                                    {
                                                        'close-up': 'Yakın Plan',
                                                        'medium distance': 'Orta Mesafe',
                                                        'full-body distance': 'Uzak Mesafe'
                                                    }[data.camera_distance] || ''
                                                )}
                                             </p>
                                             <InputError message={errors.camera_distance} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value={`Kadraj Doluluğu (%${data.frame_coverage})`} />
                                            <input 
                                                type="range" 
                                                min="10" max="100" 
                                                className="mt-2 w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                                value={data.frame_coverage}
                                                onChange={e => setData('frame_coverage', parseInt(e.target.value))}
                                            />
                                            <InputError message={errors.frame_coverage} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Görüntü Kalitesi (Çözünürlük)" />
                                             <select 
                                                className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                                value={data.image_resolution}
                                                onChange={e => setData('image_resolution', e.target.value)}
                                            >
                                                <option value="1K">1K (Standart)</option>
                                                <option value="2K">2K (Yüksek Kalite)</option>
                                                <option value="4K">4K (Ultra Kalite)</option>
                                            </select>
                                            <p className="text-xs text-gray-500 mt-1">4K daha uzun sürer.</p>
                                        </div>
                                    </div>
                                </div>

                                {/* 2. Camera & Lens */}
                                <div className="space-y-4">
                                    <div className="flex justify-between items-center border-b pb-2">
                                        <h3 className="text-lg font-medium text-gray-900">2. Kamera & Lens</h3>
                                        <button 
                                            type="button" 
                                            onClick={() => setLensLocked(!lensLocked)}
                                            className="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                                        >
                                            {lensLocked ? 'Manuel Kontrolü Aç' : 'Otomatik Modu Kilitle'}
                                        </button>
                                    </div>
                                    <div>
                                        <InputLabel value="Lens Tipi" />
                                        <select 
                                                className={`mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm ${lensLocked ? 'bg-gray-100 cursor-not-allowed text-gray-500' : ''}`}
                                                value={data.lens_type}
                                                onChange={e => setData('lens_type', e.target.value)}
                                                disabled={lensLocked}
                                            >
                                                {LENS_TYPES.map(l => <option key={l} value={l}>{l}</option>)}
                                        </select>
                                        <p className="text-xs text-gray-500 mt-1">
                                            {lensLocked ? 'Çerçeveleme kuralına göre otomatik hesaplandı.' : 'Manuel seçim aktif.'}
                                        </p>
                                        <InputError message={errors.lens_type} className="mt-2" />
                                    </div>
                                </div>

                                {/* 3. Identity & Reference - RESTORED FILE INPUT */}
                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium text-gray-900 border-b pb-2">3. Kimlik & Referanslar</h3>
                                    <div>
                                        <InputLabel value="Kimlik Referans Görselleri (Zorunlu)" />
                                        <input 
                                            type="file" 
                                            multiple 
                                            accept="image/*"
                                            className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                            onChange={e => handleFileChange('identity_reference_images', e.target.files)}
                                            required
                                        />
                                        <p className="text-xs text-gray-400 mt-1">Kendi AI modelinizi oluşturmak için 1-5 adet net fotoğraf yükleyin.</p>
                                        {/* Since 'errors' type is dynamic, need to cast or access via key string if TS complains, but useForm handles it usually */}
                                        <InputError message={errors.identity_reference_images as string} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel value="Kıyafet Referans Görselleri (Opsiyonel)" />
                                        <input 
                                            type="file" 
                                            multiple 
                                            accept="image/*"
                                            className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                            onChange={e => handleFileChange('clothing_reference_images', e.target.files)}
                                        />
                                        <InputError message={errors.clothing_reference_images as string} className="mt-2" />
                                    </div>
                                </div>

                                {/* Other Details */}
                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium text-gray-900 border-b pb-2">4. Sahne & Poz</h3>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="md:col-span-2">
                                            <InputLabel value="Mekan Açıklaması (Prompt)" />
                                            <TextInput 
                                                value={data.location_description}
                                                onChange={e => setData('location_description', e.target.value)}
                                                className="mt-1 block w-full"
                                                placeholder="Örn: luxury apartment living room"
                                            />
                                             <InputError message={errors.location_description} className="mt-2" />
                                        </div>
                                        <div>
                                            <InputLabel value="Aktivite / Eylem (Prompt)" />
                                            <TextInput 
                                                value={data.activity_style}
                                                onChange={e => setData('activity_style', e.target.value)}
                                                className="mt-1 block w-full"
                                                placeholder="Örn: walking casually"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel value="Poz Stili (Prompt)" />
                                            <TextInput 
                                                value={data.pose_style}
                                                onChange={e => setData('pose_style', e.target.value)}
                                                className="mt-1 block w-full"
                                                placeholder="Örn: relaxed editorial pose"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel value="Bakış Yönü (Prompt)" />
                                            <TextInput 
                                                value={data.gaze_direction}
                                                onChange={e => setData('gaze_direction', e.target.value)}
                                                className="mt-1 block w-full"
                                                placeholder="Örn: directly into the camera"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="pt-4">
                                    <PrimaryButton disabled={processing} className="w-full justify-center py-3 text-lg">
                                        {processing ? 'Oluşturuluyor...' : 'Influencer Fotoğrafı Oluştur'}
                                    </PrimaryButton>
                                </div>
                           </form>
                        </div>

                        {/* Results Section */}
                        <div className="lg:col-span-1">
                             <div className="sticky top-6 space-y-6" ref={resultsRef}>
                                {flash.error && (
                                    <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                                        <div className="flex">
                                            <div className="flex-shrink-0">
                                                <svg className="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <p className="text-sm text-red-700">{flash.error}</p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {processing && (
                                    <GenerationProgress processing={processing} progressPercentage={progress?.percentage} />
                                )}

                                {Object.keys(errors).length > 0 && (
                                    <div className="bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                                        <div className="flex">
                                            <div className="flex-shrink-0">
                                                <svg className="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <p className="text-sm text-red-700 font-medium">Lütfen formdaki hataları kontrol edin.</p>
                                                <ul className="mt-1 text-xs text-red-600 list-disc list-inside">
                                                    {Object.entries(errors).map(([key, value]) => (
                                                        <li key={key}>{value as string}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {(flash.generation_result || (props as any).latest_generation) && !processing && (
                                    <div className="animate-fade-in-up">
                                        <ResultDisplay 
                                            generation={flash.generation_result || (props as any).latest_generation}
                                            error={undefined}
                                            onCreateVideo={() => setShowVideoForm(true)}
                                        />
                                        
                                        {showVideoForm && (
                                            <div className="mt-8">
                                                <VideoGenerationForm 
                                                    generationId={(flash.generation_result || (props as any).latest_generation).id}
                                                    routeName="apps.ai-influencer.generate-video"
                                                    originalPrompt={data.location_description}
                                                />
                                            </div>
                                        )}
                                    </div>
                                )}
                                
                                {!flash.generation_result && !processing && (
                                    <div className="bg-gradient-to-br from-indigo-50 to-blue-50 rounded-lg p-6 border border-indigo-100 text-center">
                                        <div className="mx-auto w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-sm mb-3">
                                            <svg className="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                        <h4 className="text-indigo-900 font-medium">Ready to Create</h4>
                                        <p className="text-indigo-600 text-sm mt-1">
                                            Fill out the form to generate a professional AI influencer photo using your own references.
                                        </p>
                                    </div>
                                )}
                             </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
