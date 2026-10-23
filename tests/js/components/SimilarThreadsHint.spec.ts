import { mount } from '@vue/test-utils';
import SimilarThreadsHint from '@/components/forum/SimilarThreadsHint.vue';

describe('SimilarThreadsHint', () => {
    it('stays out of the way while there is nothing similar', () => {
        const wrapper = mount(SimilarThreadsHint, { props: { threads: [] } });

        expect(wrapper.html()).toBe('<!--v-if-->');
    });

    it('lists the similar threads, opening them in a new tab', () => {
        const wrapper = mount(SimilarThreadsHint, {
            props: {
                threads: [
                    {
                        id: 4,
                        title: '¿Cómo testear colas?',
                        slug: 'como-testear-colas',
                        replies_count: 3,
                        is_solved: true,
                    },
                    {
                        id: 5,
                        title: 'Colas con Redis',
                        slug: 'colas-con-redis',
                        replies_count: 0,
                        is_solved: false,
                    },
                ],
            },
        });

        const links = wrapper.findAll('a');

        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/t/como-testear-colas',
            '/t/colas-con-redis',
        ]);
        expect(links[0].attributes('target')).toBe('_blank');
        expect(links[0].attributes('rel')).toBe('noopener');
        expect(wrapper.findAll('[data-test="similar-solved"]')).toHaveLength(1);
        expect(wrapper.text()).toContain('3');
    });
});
