import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { toast } from 'react-hot-toast';
import { router } from '@inertiajs/react';

interface GenerationCompletedEvent {
    generation_id: number;
    status: string;
    model_name: string;
    model_slug?: string;
    thumbnail_url?: string;
    result?: string;
}

export function useGenerationNotifications() {
    const { auth } = usePage().props as any;
    const userId = auth?.user?.id;

    useEffect(() => {
        if (!userId) {
            console.warn('[GenerationNotifications] No user ID found');
            return;
        }
        
        if (!window.Echo) {
            console.error('[GenerationNotifications] Echo instance is missing');
            return;
        }


        // Subscribe to user's private channel
        const channel = window.Echo.private(`user.${userId}`);

        // Listen for generation completed events
    channel.listen('.generation.completed', (event: GenerationCompletedEvent) => {

            if (event.status === 'completed') {
                // Show success toast
                toast.success(
                    (t: any) => (
                        <div 
                            className="flex items-center gap-3 cursor-pointer"
                            onClick={() => {
                                if (event.model_slug) {
                                    router.visit(`/models/${event.model_slug}?reprompt=${event.generation_id}`);
                                }
                                toast.dismiss(t.id);
                            }}
                        >
                            {event.thumbnail_url && (
                                <img 
                                    src={event.thumbnail_url} 
                                    alt="Generated" 
                                    className="w-12 h-12 rounded object-cover"
                                />
                            )}
                            <div>
                                <p className="font-semibold">Generation Complete!</p>
                                <p className="text-sm text-gray-600">{event.model_name}</p>
                            </div>
                        </div>
                    ),
                    {
                        duration: 5000,
                        style: {
                            minWidth: '250px',
                        },
                    }
                );
            } else if (event.status === 'failed') {
                // Show error toast
                toast.error(`Generation failed for ${event.model_name}`, {
                    duration: 5000,
                });
            }

            // Dispatch custom event for other components (e.g. Notification Bell)
            window.dispatchEvent(new CustomEvent('notification-received', { 
                detail: { ...event, timestamp: new Date().toISOString() } 
            }));
        });

        // Listen for app execution completed events
        channel.listen('.app.execution.completed', (event: any) => {
            if (event.status === 'completed') {
                toast.success(
                    (t: any) => (
                        <div 
                            className="flex items-center gap-3 cursor-pointer"
                            onClick={() => {
                                router.visit(`/apps/${event.app_slug}?execution_id=${event.execution_id}`);
                                toast.dismiss(t.id);
                            }}
                        >
                            <div className="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center text-xl">
                                ✨
                            </div>
                            <div>
                                <p className="font-semibold">Application Complete!</p>
                                <p className="text-sm text-gray-600">{event.app_name} is ready.</p>
                            </div>
                        </div>
                    ),
                    { duration: 8000 }
                );
            } else if (event.status === 'failed') {
                toast.error(`Application execution failed: ${event.app_name}`, {
                    duration: 8000
                });
            }

            window.dispatchEvent(new CustomEvent('notification-received', { 
                detail: { ...event, type: 'app_execution', timestamp: new Date().toISOString() } 
            }));
        });

        // Cleanup on unmount
        return () => {
            channel.stopListening('.generation.completed');
            channel.stopListening('.app.execution.completed');
            window.Echo.leave(`user.${userId}`);
        };
    }, [userId]);
}
