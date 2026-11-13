import { useCallback, useEffect, useRef, useState } from 'react';
import type { PresenceChannel } from '@ably/laravel-echo';

export type WorkspaceMember = {
    id: number;
    name: string;
};

type TypingWhisper = {
    active: true;
};

function isTypingWhisper(payload: unknown): payload is TypingWhisper {
    return (
        typeof payload === 'object' &&
        payload !== null &&
        'active' in payload &&
        payload.active === true
    );
}

export function useWorkspacePresence(userId: number | undefined) {
    const [onlineUsers, setOnlineUsers] = useState<WorkspaceMember[]>([]);
    const [typingUsers, setTypingUsers] = useState<WorkspaceMember[]>([]);
    const [status, setStatus] = useState<'connecting' | 'connected' | 'error'>(
        'connecting',
    );
    const channelRef = useRef<PresenceChannel | null>(null);
    const membersRef = useRef<WorkspaceMember[]>([]);
    const typingTimers = useRef(
        new Map<number, ReturnType<typeof setTimeout>>(),
    );
    const lastWhisperAt = useRef(0);

    useEffect(() => {
        if (!userId) {
            return;
        }

        const echo = window.Echo;
        const channel = echo.join('workspace');
        channelRef.current = channel;

        const updateMembers = (members: WorkspaceMember[]) => {
            const colleagues = members.filter((member) => member.id !== userId);
            membersRef.current = colleagues;
            setOnlineUsers(colleagues);
            setStatus('connected');
        };

        const removeMember = (member: WorkspaceMember) => {
            const colleagues = membersRef.current.filter(
                (onlineUser) => onlineUser.id !== member.id,
            );
            membersRef.current = colleagues;
            setOnlineUsers(colleagues);
            setTypingUsers((current) =>
                current.filter((typingUser) => typingUser.id !== member.id),
            );
            const timer = typingTimers.current.get(member.id);
            if (timer) {
                clearTimeout(timer);
                typingTimers.current.delete(member.id);
            }
        };

        channel.here(updateMembers);
        channel.joining((member: WorkspaceMember) => {
            updateMembers([...membersRef.current, member]);
        });
        channel.leaving(removeMember);
        channel.listenForWhisper(
            'typing',
            (payload: unknown, metadata: { clientId?: string }) => {
                if (!isTypingWhisper(payload) || !metadata.clientId) {
                    return;
                }

                const senderId = Number(metadata.clientId);
                const member = membersRef.current.find(
                    (onlineUser) => onlineUser.id === senderId,
                );

                if (!member) {
                    return;
                }

                const previousTimer = typingTimers.current.get(senderId);
                if (previousTimer) {
                    clearTimeout(previousTimer);
                }

                setTypingUsers((current) =>
                    current.some((typingUser) => typingUser.id === senderId)
                        ? current
                        : [...current, member],
                );

                typingTimers.current.set(
                    senderId,
                    setTimeout(() => {
                        setTypingUsers((current) =>
                            current.filter(
                                (typingUser) => typingUser.id !== senderId,
                            ),
                        );
                        typingTimers.current.delete(senderId);
                    }, 2500),
                );
            },
        );
        channel.error(() => setStatus('error'));

        return () => {
            channelRef.current = null;
            membersRef.current = [];
            typingTimers.current.forEach((timer) => clearTimeout(timer));
            typingTimers.current.clear();
            setTypingUsers([]);
            echo.leave('workspace');
        };
    }, [userId]);

    const notifyTyping = useCallback(() => {
        if (status !== 'connected') {
            return;
        }

        const now = Date.now();
        if (now - lastWhisperAt.current < 1200) {
            return;
        }

        const channel = channelRef.current;
        if (!channel) {
            return;
        }

        lastWhisperAt.current = now;
        channel.whisper('typing', { active: true });
    }, [status]);

    return { onlineUsers, typingUsers, status, notifyTyping };
}
