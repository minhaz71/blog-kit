export default function tableCellTextEditingExtension() {
    const { Extension } = window.FilamentRichEditor.tiptap.core;
    const { Plugin, TextSelection } = window.FilamentRichEditor.tiptap.pmState;
    const uploadRecoveryTimeout = 90_000;

    return Extension.create({
        name: 'shopkitTableCellTextEditing',

        // REPAIR INVALID DOCUMENTS ON LOAD. The server-side HTML-to-editor
        // conversion emits table cells (and list items) whose children are
        // bare text nodes — but the editor schema requires block content
        // (a paragraph) inside them. The editor accepts the invalid document
        // silently, then EVERY edit inside such a cell throws
        // "Called contentMatchAt on a node with invalid content" — which is
        // why no caret could be placed in tables. Wrapping the stray inline
        // runs in paragraphs makes the document valid, and native table
        // editing works from there.
        onCreate() {
            try {
                const editor = this.editor;
                const schema = editor.state.schema;
                let changed = false;

                const isInlineChild = (child) =>
                    child.type === 'text' || (schema.nodes[child.type]?.isInline ?? false);

                const fix = (node) => {
                    if (! Array.isArray(node.content) || node.content.length === 0) {
                        return node;
                    }

                    let content = node.content.map(fix);
                    const nodeType = schema.nodes[node.type];

                    // Only repair nodes whose schema expects BLOCK children but
                    // whose stored content includes inline children.
                    if (nodeType && ! nodeType.spec.inline && ! nodeType.inlineContent
                        && content.some(isInlineChild)) {
                        const wrapped = [];
                        let run = [];
                        const flush = () => {
                            if (run.length) {
                                wrapped.push({ type: 'paragraph', content: run });
                                run = [];
                            }
                        };
                        for (const child of content) {
                            if (isInlineChild(child)) {
                                run.push(child);
                            } else {
                                flush();
                                wrapped.push(child);
                            }
                        }
                        flush();
                        content = wrapped;
                        changed = true;
                    }

                    return { ...node, content };
                };

                const fixed = fix(editor.getJSON());

                if (changed) {
                    editor.commands.setContent(fixed);
                }
            } catch (error) {
                console.warn('ShopKit table repair skipped:', error);
            }
        },

        addProseMirrorPlugins() {
            return [
                new Plugin({
                    view(editorView) {
                        let activeUploads = 0;
                        let recoveryTimer = null;
                        const form = editorView.dom.closest('form');

                        const clearRecoveryTimer = () => {
                            if (recoveryTimer === null) {
                                return;
                            }

                            window.clearTimeout(recoveryTimer);
                            recoveryTimer = null;
                        };

                        const releaseStuckUpload = () => {
                            if (activeUploads === 0) {
                                return;
                            }

                            activeUploads = 0;
                            clearRecoveryTimer();
                            editorView.setProps({ editable: () => true });
                            form?.dispatchEvent(new CustomEvent('form-processing-finished', { bubbles: true }));
                        };

                        const scheduleRecovery = () => {
                            clearRecoveryTimer();
                            recoveryTimer = window.setTimeout(releaseStuckUpload, uploadRecoveryTimeout);
                        };

                        const handleUploadStarted = () => {
                            activeUploads += 1;
                            scheduleRecovery();
                        };

                        const handleUploadFinished = () => {
                            activeUploads = Math.max(0, activeUploads - 1);

                            if (activeUploads === 0) {
                                clearRecoveryTimer();
                            }
                        };

                        editorView.dom.addEventListener('rich-editor-uploading-file', handleUploadStarted);
                        editorView.dom.addEventListener('rich-editor-uploaded-file', handleUploadFinished);

                        const livewireElement = editorView.dom.closest('[wire\\:id]');
                        const livewire = livewireElement
                            ? window.Livewire?.find(livewireElement.getAttribute('wire:id'))
                            : null;
                        const stopInterceptingUploadErrors = livewire?.$interceptAction?.(
                            '_uploadErrored',
                            ({ onFinish }) => {
                                if (activeUploads > 0) {
                                    onFinish(releaseStuckUpload);
                                }
                            },
                        );

                        return {
                            destroy() {
                                clearRecoveryTimer();
                                editorView.dom.removeEventListener('rich-editor-uploading-file', handleUploadStarted);
                                editorView.dom.removeEventListener('rich-editor-uploaded-file', handleUploadFinished);
                                stopInterceptingUploadErrors?.();
                            },
                        };
                    },
                    props: {
                        handleDOMEvents: {
                            click(view, event) {
                                if (
                                    event.button !== 0 ||
                                    event.altKey ||
                                    event.ctrlKey ||
                                    event.metaKey ||
                                    event.shiftKey ||
                                    !(event.target instanceof Element)
                                ) {
                                    return false;
                                }

                                const cell = event.target.closest('td, th');

                                if (!cell || !view.dom.contains(cell)) {
                                    return false;
                                }

                                if (event.target.closest('a, button, input, select, textarea, [contenteditable="false"]')) {
                                    return false;
                                }

                                const currentSelection = view.state.selection;

                                // Keep an intentional multi-cell selection available for
                                // merge, split, row, and column operations.
                                if (
                                    currentSelection.$anchorCell &&
                                    currentSelection.$headCell &&
                                    currentSelection.$anchorCell.pos !== currentSelection.$headCell.pos
                                ) {
                                    return false;
                                }

                                const hit = view.posAtCoords({
                                    left: event.clientX,
                                    top: event.clientY,
                                });

                                if (!hit) {
                                    return false;
                                }

                                const resolvedPosition = view.state.doc.resolve(hit.pos);
                                let isInsideTableCell = false;

                                for (let depth = resolvedPosition.depth; depth > 0; depth -= 1) {
                                    if (['tableCell', 'tableHeader'].includes(resolvedPosition.node(depth).type.name)) {
                                        isInsideTableCell = true;
                                        break;
                                    }
                                }

                                if (!isInsideTableCell) {
                                    return false;
                                }

                                const selection = TextSelection.near(resolvedPosition);

                                view.dispatch(view.state.tr.setSelection(selection).scrollIntoView());
                                view.focus();

                                return true;
                            },
                        },
                    },
                }),
            ];
        },
    });
}
