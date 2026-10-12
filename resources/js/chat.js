/**
 * Real-time chat: sends with fetch, receives over Laravel Echo (Reverb).
 * Messages are rendered with textContent only, never innerHTML, so message
 * bodies cannot inject markup.
 */
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

function timeLabel(iso) {
    return new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function renderMessage(m, me) {
    const mine = m.user.id === me;
    const li = document.createElement('li');
    li.dataset.id = m.id;
    li.className = `flex ${mine ? 'justify-end' : 'justify-start'} group`;

    const bubble = document.createElement('div');
    bubble.className = `max-w-[75%] rounded-2xl px-3 py-2 ${mine ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-slate-100 rounded-bl-sm'}`;

    const body = document.createElement('p');
    body.className = 'whitespace-pre-line break-words text-sm';
    body.textContent = m.body;

    const meta = document.createElement('div');
    meta.className = `flex items-center gap-2 mt-0.5 text-[11px] ${mine ? 'text-indigo-100' : 'text-slate-500'}`;
    const time = document.createElement('time');
    time.dateTime = m.created_at;
    time.textContent = timeLabel(m.created_at);
    meta.append(time);

    if (mine) {
        const del = document.createElement('button');
        del.type = 'button';
        del.dataset.delete = m.id;
        del.className = 'opacity-0 group-hover:opacity-100 focus:opacity-100 underline';
        del.textContent = 'delete';
        meta.append(del);
    }

    bubble.append(body, meta);
    li.append(bubble);
    return li;
}

export function initChat() {
    const root = document.getElementById('chat');
    if (!root) return;

    const me = Number(root.dataset.me);
    const list = root.querySelector('[data-messages]');
    const form = root.querySelector('[data-composer]');
    const input = form.querySelector('input[name="body"]');

    const scrollDown = () => { list.scrollTop = list.scrollHeight; };
    const append = (m) => {
        if (list.querySelector(`[data-id="${m.id}"]`)) return;
        list.querySelector('[data-empty]')?.remove();
        list.append(renderMessage(m, me));
        scrollDown();
    };
    scrollDown();

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        input.value = '';
        input.disabled = true;
        try {
            const res = await fetch(root.dataset.sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Socket-ID': window.Echo?.socketId() ?? '',
                },
                body: JSON.stringify({ body }),
            });
            if (!res.ok) throw new Error((await res.json().catch(() => ({}))).message || 'Could not send');
            append(await res.json());
        } catch (err) {
            input.value = body;
            alert(err.message);
        } finally {
            input.disabled = false;
            input.focus();
        }
    });

    list.addEventListener('click', async (e) => {
        const id = e.target.dataset?.delete;
        if (!id || !confirm('Delete this message for everyone?')) return;
        const res = await fetch(`${root.dataset.deleteUrl}/${id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Socket-ID': window.Echo?.socketId() ?? '' },
        });
        if (res.ok) list.querySelector(`[data-id="${id}"]`)?.remove();
    });

    if (window.Echo) {
        window.Echo.private(`conversations.${root.dataset.conversation}`)
            .listen('.message.sent', (m) => {
                append(m);
                fetch(root.dataset.readUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            })
            .listen('.message.deleted', ({ id }) => list.querySelector(`[data-id="${id}"]`)?.remove());
    }
}
