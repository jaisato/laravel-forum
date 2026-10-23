import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { useDraft } from '@/composables/useDraft';

function withDraft(key: string): ReturnType<typeof useDraft> {
    let draft!: ReturnType<typeof useDraft>;

    mount(
        defineComponent({
            setup() {
                draft = useDraft(key);

                return () => h('div');
            },
        }),
    );

    return draft;
}

describe('useDraft', () => {
    beforeEach(() => localStorage.clear());

    it('restores a saved draft once mounted', () => {
        localStorage.setItem('forum:draft:reply:1', 'Texto guardado');

        expect(withDraft('reply:1').text.value).toBe('Texto guardado');
    });

    it('saves what is typed', async () => {
        const draft = withDraft('reply:2');

        draft.text.value = 'Hola';
        await nextTick();

        expect(localStorage.getItem('forum:draft:reply:2')).toBe('Hola');
    });

    it('forgets the draft when cleared', async () => {
        const draft = withDraft('reply:3');
        draft.text.value = 'Hola';
        await nextTick();

        draft.clear();
        await nextTick();

        expect(draft.text.value).toBe('');
        expect(localStorage.getItem('forum:draft:reply:3')).toBeNull();
    });

    it('keeps drafts of different forms apart', async () => {
        const reply = withDraft('reply:4');
        const thread = withDraft('thread:new');

        reply.text.value = 'Respuesta';
        await nextTick();

        expect(thread.text.value).toBe('');
        expect(localStorage.getItem('forum:draft:thread:new')).toBeNull();
    });
});
