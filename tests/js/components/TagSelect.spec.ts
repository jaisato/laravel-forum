import { mount } from '@vue/test-utils';
import TagSelect from '@/components/forum/TagSelect.vue';
import type { TagOption } from '@/types/forum';

const tags: TagOption[] = [1, 2, 3, 4, 5, 6].map((id) => ({
    id,
    name: `etiqueta-${id}`,
    slug: `etiqueta-${id}`,
    color: id === 1 ? '#ef4444' : null,
}));

function select(modelValue: number[], max?: number) {
    const wrapper = mount(TagSelect, {
        props: {
            tags,
            modelValue,
            max,
            'onUpdate:modelValue': (value: number[]) =>
                wrapper.setProps({ modelValue: value }),
        },
    });

    return wrapper;
}

const option = (wrapper: ReturnType<typeof select>, id: number) =>
    wrapper.get(`[data-test="tag-option-${id}"]`);

describe('TagSelect', () => {
    it('adds and removes tags', async () => {
        const wrapper = select([]);

        await option(wrapper, 2).trigger('click');
        await option(wrapper, 4).trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[2, 4]]);

        await option(wrapper, 2).trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[4]]);
    });

    it('marks the chosen tags as pressed and counts them', () => {
        const wrapper = select([1, 3]);

        expect(option(wrapper, 1).attributes('aria-pressed')).toBe('true');
        expect(option(wrapper, 2).attributes('aria-pressed')).toBe('false');
        expect(wrapper.get('[data-test="tag-count"]').text()).toBe('2 / 5');
    });

    it('stops at the maximum but still lets tags go', async () => {
        const wrapper = select([1, 2, 3, 4, 5]);

        expect(option(wrapper, 6).attributes('disabled')).toBeDefined();
        expect(option(wrapper, 5).attributes('disabled')).toBeUndefined();

        await option(wrapper, 5).trigger('click');

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            [1, 2, 3, 4],
        ]);
        expect(option(wrapper, 6).attributes('disabled')).toBeUndefined();
    });

    it('takes another maximum', () => {
        const wrapper = select([1], 1);

        expect(option(wrapper, 2).attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-test="tag-count"]').text()).toBe('1 / 1');
    });

    it('says when there are no tags to choose', () => {
        const wrapper = mount(TagSelect, {
            props: { tags: [], modelValue: [] },
        });

        expect(wrapper.text()).toContain('There are no tags yet.');
    });
});
