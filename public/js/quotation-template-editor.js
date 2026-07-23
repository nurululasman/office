(() => {
    'use strict';

    const initialize = () => {
        const textarea = document.getElementById('content_html');
        if (! textarea) {
            return;
        }

        const fallback = document.getElementById('tinymce-fallback');
        if (! window.tinymce) {
            fallback?.classList.remove('d-none');

            return;
        }

        window.tinymce.init({
            target: textarea,
            base_url: '/libs/tinymce',
            suffix: '.min',
            skin: 'oxide',
            content_css: 'document',
            height: 640,
            min_height: 480,
            menubar: 'edit view insert format table tools',
            plugins: 'advlist autolink autoresize code lists pagebreak preview searchreplace table visualblocks wordcount',
            toolbar: [
                'undo redo | blocks | bold italic underline',
                'alignleft aligncenter alignright alignjustify',
                'bullist numlist | table pagebreak',
                'removeformat | placeholders | searchreplace visualblocks code preview | wordcount',
            ].join(' | '),
            toolbar_mode: 'sliding',
            statusbar: true,
            branding: false,
            promotion: false,
            browser_spellcheck: true,
            contextmenu: false,
            convert_urls: false,
            relative_urls: false,
            remove_script_host: false,
            paste_as_text: true,
            pagebreak_separator: '<hr class="page-break">',
            object_resizing: 'table',
            table_use_colgroups: true,
            table_default_attributes: {
                border: '1',
            },
            table_default_styles: {
                'border-collapse': 'collapse',
                width: '100%',
            },
            valid_elements: [
                'p[style|class]',
                'br',
                'strong/b',
                'em/i',
                'u',
                's',
                'h1[style|class]',
                'h2[style|class]',
                'h3[style|class]',
                'h4[style|class]',
                'blockquote[style|class]',
                'ul[style|class]',
                'ol[style|class|start]',
                'li[style|class]',
                'table[style|class|border]',
                'thead[style|class]',
                'tbody[style|class]',
                'tfoot[style|class]',
                'tr[style|class]',
                'th[style|class|colspan|rowspan|scope]',
                'td[style|class|colspan|rowspan]',
                'div[style|class]',
                'span[style|class]',
                'hr[class]',
            ].join(','),
            valid_styles: {
                '*': 'text-align',
                p: 'margin-left',
                h1: 'margin-left',
                h2: 'margin-left',
                h3: 'margin-left',
                h4: 'margin-left',
                table: 'border-collapse,width',
                th: 'text-align,vertical-align',
                td: 'text-align,vertical-align',
            },
            content_style: [
                'body { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; line-height: 1.4; margin: 16px; }',
                'table { border-collapse: collapse; width: 100%; }',
                'th, td { border: 1px solid #7b8794; padding: 6px; }',
                '.mce-pagebreak { border-top: 2px dashed #8b96a3; }',
            ].join(' '),
            setup: (editor) => {
                const scalarPlaceholders = [
                    ['Nomor quotation', 'quotation_number'],
                    ['Tanggal quotation', 'quotation_date'],
                    ['Subjek', 'subject'],
                    ['Nama pelanggan', 'customer_name'],
                    ['Alamat pelanggan', 'customer_address'],
                    ['Nama attention', 'attention_name'],
                    ['Jabatan attention', 'attention_role'],
                    ['Nama pengirim', 'sender_name'],
                    ['Jabatan pengirim', 'sender_title'],
                    ['Mata uang', 'currency'],
                    ['Teks pengantar', 'intro_text'],
                    ['Teks penutup', 'closing_text'],
                    ['Nama legal perusahaan', 'company_legal_name'],
                    ['Nama tampilan perusahaan', 'company_display_name'],
                    ['Alamat perusahaan', 'company_address'],
                    ['Email perusahaan', 'company_email'],
                    ['Telepon perusahaan', 'company_phone'],
                    ['Website perusahaan', 'company_website'],
                ];
                const structuralPlaceholders = [
                    ['Logo perusahaan', 'company_logo'],
                    ['Tabel item quotation', 'quotation_items'],
                    ['Terms quotation', 'quotation_terms'],
                    ['Blok tanda tangan', 'signature_block'],
                    ['Watermark draft', 'draft_watermark'],
                ];

                const menuItems = (placeholders, structural = false) => placeholders.map(([text, token]) => ({
                    type: 'menuitem',
                    text,
                    onAction: () => editor.insertContent(
                        structural
                            ? `<div>{{ ${token} }}</div><p></p>`
                            : `{{ ${token} }}`,
                    ),
                }));

                editor.ui.registry.addMenuButton('placeholders', {
                    text: 'Placeholder',
                    tooltip: 'Sisipkan data quotation',
                    fetch: (callback) => callback([
                        {
                            type: 'nestedmenuitem',
                            text: 'Data',
                            getSubmenuItems: () => menuItems(scalarPlaceholders),
                        },
                        {
                            type: 'nestedmenuitem',
                            text: 'Komponen',
                            getSubmenuItems: () => menuItems(structuralPlaceholders, true),
                        },
                    ]),
                });
                editor.on('change input undo redo', () => editor.save());
            },
        }).catch(() => {
            fallback?.classList.remove('d-none');
            textarea.classList.remove('d-none');
        });

        textarea.form?.addEventListener('submit', () => {
            window.tinymce.triggerSave();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
