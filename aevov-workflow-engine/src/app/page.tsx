'use client';

import { useEffect, useState } from 'react';
import { WorkflowBuilder } from '../components/WorkflowBuilder';

export default function StandalonePage() {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        // Initialize config for standalone mode
        if (typeof window !== 'undefined' && !window.aevovWorkflowEngine) {
            window.aevovWorkflowEngine = {
                apiUrl: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8080/wp-json/aevov-workflow/v1',
                nonce: '',
                userId: 0,
                userName: 'Guest',
                isAdmin: true,
                settings: {
                    maxExecutionTime: 300,
                    maxNodes: 100,
                },
                strings: {},
            };
        }
        setMounted(true);
    }, []);

    if (!mounted) {
        return (
            <div className="flex items-center justify-center h-screen bg-[#1a1a2e]">
                <div className="text-center">
                    <div className="w-12 h-12 border-4 border-[#0ea5e9] border-t-transparent rounded-full animate-spin mx-auto mb-4" />
                    <p className="text-[#94a3b8]">Loading Aevov Workflow Engine...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="h-screen bg-[#1a1a2e]">
            <WorkflowBuilder initialWorkflowId={null} />
        </div>
    );
}
