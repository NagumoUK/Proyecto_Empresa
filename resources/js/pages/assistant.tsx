import { Head, useHttp } from '@inertiajs/react';
import { Bot, Send, Sparkles } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { ask as askAssistant } from '@/actions/App/Http/Controllers/BusinessAssistantController';
import { Button } from '@/components/ui/button';

type AskRequest = {
    question: string;
    conversation_id: string | null;
};

type AskResponse = {
    answer: string;
    conversationId: string | null;
};

type ChatMessage = {
    role: 'assistant' | 'user';
    content: string;
};

function messageFromResponse(data: unknown, fallback: string): string {
    if (
        typeof data === 'object' &&
        data !== null &&
        'message' in data &&
        typeof data.message === 'string'
    ) {
        return data.message;
    }

    return fallback;
}

export default function AssistantPage() {
    const request = useHttp<AskRequest, AskResponse>({
        question: '',
        conversation_id: null,
    });
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [error, setError] = useState<string | null>(null);

    function submitQuestion(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const question = request.data.question.trim();

        if (question.length === 0 || request.processing) {
            return;
        }

        setError(null);

        void request
            .post(askAssistant.url(), {
                onSuccess: (response) => {
                    setMessages((currentMessages) => [
                        ...currentMessages,
                        { role: 'user', content: question },
                        { role: 'assistant', content: response.answer },
                    ]);
                    request.setData({
                        question: '',
                        conversation_id: response.conversationId,
                    });
                },
                onError: (errors) => {
                    setError(
                        typeof errors.question === 'string'
                            ? errors.question
                            : 'No se pudo validar la pregunta.',
                    );
                },
                onHttpException: (response) => {
                    setError(
                        messageFromResponse(
                            response.data,
                            'El asistente no pudo procesar la pregunta.',
                        ),
                    );
                },
                onNetworkError: () => {
                    setError('No fue posible conectar con el asistente.');
                },
            })
            .catch(() => {
                setError(
                    (currentError) =>
                        currentError ??
                        'No fue posible completar la solicitud. Inténtalo nuevamente.',
                );
            });
    }

    return (
        <>
            <Head title="Asistente IA" />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <Sparkles className="size-6" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Asistente de negocio
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Consulta en lenguaje natural información
                                autorizada de clientes y proyectos.
                            </p>
                        </div>
                    </div>
                </header>

                <section className="overflow-hidden rounded-2xl border bg-card shadow-sm">
                    <div
                        aria-label="Conversación con el asistente"
                        aria-live="polite"
                        className="flex max-h-[60vh] min-h-72 flex-col gap-5 overflow-y-auto p-4 sm:p-6"
                    >
                        {messages.length === 0 ? (
                            <div className="m-auto flex max-w-md flex-col items-center gap-3 py-10 text-center">
                                <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                    <Bot
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </div>
                                <h2 className="font-medium">
                                    ¿Qué necesitas consultar?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Por ejemplo: “¿Cuántos proyectos activos
                                    tenemos y cuáles están en pausa?”
                                </p>
                            </div>
                        ) : (
                            messages.map((message, index) => (
                                <div
                                    key={`${message.role}-${index}`}
                                    className={`flex ${
                                        message.role === 'user'
                                            ? 'justify-end'
                                            : 'justify-start'
                                    }`}
                                >
                                    <p
                                        className={`max-w-[90%] rounded-2xl px-4 py-3 text-sm whitespace-pre-wrap sm:max-w-[80%] ${
                                            message.role === 'user'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-foreground'
                                        }`}
                                    >
                                        {message.content}
                                    </p>
                                </div>
                            ))
                        )}
                        {request.processing && (
                            <p
                                className="text-sm text-muted-foreground"
                                role="status"
                            >
                                Consultando información autorizada…
                            </p>
                        )}
                    </div>

                    <div className="border-t bg-background/70 p-4 sm:p-6">
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={submitQuestion}
                        >
                            <label
                                htmlFor="assistant-question"
                                className="text-sm font-medium"
                            >
                                Tu pregunta
                            </label>
                            <textarea
                                id="assistant-question"
                                value={request.data.question}
                                onChange={(event) =>
                                    request.setData(
                                        'question',
                                        event.target.value,
                                    )
                                }
                                placeholder="Escribe una pregunta sobre clientes o proyectos…"
                                maxLength={2000}
                                rows={3}
                                className="w-full resize-y rounded-xl border border-input bg-background px-4 py-3 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                disabled={request.processing}
                                aria-invalid={Boolean(error)}
                                aria-describedby="assistant-privacy assistant-error"
                            />
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p
                                    id="assistant-privacy"
                                    className="max-w-2xl text-xs text-muted-foreground"
                                >
                                    Gemini gratuito es solo para pruebas con
                                    datos ficticios o anonimizados: Google puede
                                    usar las solicitudes para mejorar sus
                                    servicios. No incluyas datos reales,
                                    personales ni confidenciales.
                                </p>
                                <Button
                                    type="submit"
                                    disabled={
                                        request.processing ||
                                        request.data.question.trim().length ===
                                            0
                                    }
                                    className="shrink-0"
                                >
                                    {request.processing
                                        ? 'Consultando…'
                                        : 'Enviar pregunta'}
                                    <Send
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </div>
                            <p
                                id="assistant-error"
                                className="text-sm text-destructive"
                                role="alert"
                            >
                                {error}
                            </p>
                        </form>
                    </div>
                </section>
            </div>
        </>
    );
}
