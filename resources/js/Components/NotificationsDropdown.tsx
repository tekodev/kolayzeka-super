import React, { useState, useEffect, useRef } from 'react';
import { Bell } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Notification {
    id: string;
    generation_id: number;
    status: 'completed' | 'failed';
    model_name: string;
    model_slug?: string;
    thumbnail_url?: string;
    timestamp: string;
    read: boolean;
}

function timeAgo(dateString: string) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return date.toLocaleDateString(); 
}

export default function NotificationsDropdown() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef<HTMLDivElement>(null);

    // Load from local storage on mount
    useEffect(() => {
        const stored = localStorage.getItem('user_notifications');
        if (stored) {
            try {
                setNotifications(JSON.parse(stored));
            } catch (e) {
                console.error('Failed to parse notifications', e);
            }
        }
    }, []);

    // Save to local storage on change
    useEffect(() => {
        localStorage.setItem('user_notifications', JSON.stringify(notifications));
    }, [notifications]);

    // Close on click outside
    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Listen for new notifications
    useEffect(() => {
        const handleNewNotification = (e: CustomEvent) => {
            const data = e.detail;
            const newNotification: Notification = {
                id: Math.random().toString(36).substr(2, 9),
                generation_id: data.generation_id,
                status: data.status,
                model_name: data.model_name,
                model_slug: data.model_slug,
                thumbnail_url: data.thumbnail_url,
                timestamp: data.timestamp || new Date().toISOString(),
                read: false,
            };
            setNotifications(prev => [newNotification, ...prev]);
        };

        window.addEventListener('notification-received' as any, handleNewNotification);
        return () => window.removeEventListener('notification-received' as any, handleNewNotification);
    }, []);

    const unreadCount = notifications.filter(n => !n.read).length;

    const markAllAsRead = () => {
        setNotifications(prev => prev.map(n => ({ ...n, read: true })));
    };

    const clearNotifications = () => {
        setNotifications([]);
        setIsOpen(false);
    };

    const toggleOpen = () => {
        if (!isOpen) {
            // Mark as read when opening? Optionally.
            // markAllAsRead(); 
        }
        setIsOpen(!isOpen);
    };

    return (
        <div className="relative ml-3" ref={dropdownRef}>
            <button
                onClick={toggleOpen}
                className="relative p-2 text-gray-400 hover:text-gray-500 focus:outline-none transition-colors"
                aria-label="Notifications"
            >
                <Bell size={24} />
                {unreadCount > 0 && (
                    <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white ring-2 ring-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="absolute right-0 mt-2 w-80 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 overflow-hidden transform transition-all duration-200 ease-out">
                    <div className="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 className="text-sm font-semibold text-gray-900">Notifications</h3>
                        <div className="flex gap-3 text-xs font-medium">
                             {unreadCount > 0 && (
                                <button onClick={markAllAsRead} className="text-indigo-600 hover:text-indigo-800 transition-colors">
                                    Read all
                                </button>
                             )}
                             {notifications.length > 0 && (
                                <button onClick={clearNotifications} className="text-gray-500 hover:text-red-600 transition-colors">
                                    Clear
                                </button>
                             )}
                        </div>
                    </div>
                    
                    <div className="max-h-[24rem] overflow-y-auto custom-scrollbar">
                        {notifications.length === 0 ? (
                            <div className="p-8 text-center">
                                <Bell className="mx-auto h-8 w-8 text-gray-300 mb-2" />
                                <p className="text-sm text-gray-500">No notifications yet</p>
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-100">
                                {notifications.map(notification => (
                                    <div 
                                        key={notification.id} 
                                        onClick={() => {
                                            if (notification.model_slug) {
                                                setIsOpen(false);
                                                router.visit(`/models/${notification.model_slug}?reprompt=${notification.generation_id}`);
                                            }
                                        }}
                                        className={`p-4 hover:bg-gray-50 transition-colors flex gap-3 cursor-pointer ${notification.read ? 'opacity-60' : 'bg-blue-50/40'}`}
                                    >
                                        <div className={`mt-1.5 h-2 w-2 rounded-full flex-shrink-0 ${notification.status === 'completed' ? 'bg-green-500' : 'bg-red-500'}`} />
                                        
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900">
                                                {notification.status === 'completed' ? 'Generation Complete' : 'Generation Failed'}
                                            </p>
                                            <p className="text-xs text-gray-500 truncate mb-1">
                                                {notification.model_name}
                                            </p>
                                            <p className="text-[10px] text-gray-400">
                                                {timeAgo(notification.timestamp)}
                                            </p>
                                        </div>

                                        {notification.thumbnail_url && (
                                            <div className="block flex-shrink-0">
                                                <img 
                                                    src={notification.thumbnail_url} 
                                                    alt="Result" 
                                                    className="h-12 w-12 rounded-md object-cover border border-gray-200"
                                                />
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
