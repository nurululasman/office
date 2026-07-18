import './bootstrap';
import Alpine from 'alpinejs';

Alpine.data('documentTypeBuilder', (initialSegments = []) => ({
    segments: initialSegments.length ? initialSegments : [
        { type: 'literal', value: 'DOC-' },
        { type: 'token', value: 'YYYY' },
        { type: 'sequence', width: 4 },
    ],
    preview: '',
    pattern: '',
    error: '',
    init() {
        this.$watch('segments', () => this.refreshPreview(), { deep: true });
        this.refreshPreview();
    },
    add(type) {
        this.segments.push(type === 'literal'
            ? { type: 'literal', value: '-' }
            : type === 'sequence'
                ? { type: 'sequence', width: 4 }
                : { type: 'token', value: 'YYYY' });
    },
    remove(index) {
        this.segments.splice(index, 1);
    },
    async refreshPreview() {
        this.error = '';
        try {
            const response = await fetch(document.querySelector('[data-preview-url]').dataset.previewUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ segments: this.segments }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? 'Preview tidak dapat dibuat.');
            this.preview = data.preview;
            this.pattern = data.pattern;
        } catch (error) {
            this.preview = '';
            this.pattern = '';
            this.error = error.message;
        }
    },
}));

window.Alpine = Alpine;

Alpine.start();
