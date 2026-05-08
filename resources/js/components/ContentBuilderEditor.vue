<template>
    <div class="content-builder-editor">
        <div class="content-builder-toolbar">
            <div>
                <div class="content-builder-hint">
                    {{ $t('content_builder_blocks_count', { count: blocks.length }) }}
                </div>
            </div>

            <div class="content-builder-actions">
                <select
                    v-model="selectedBlockId"
                    class="form-control"
                    @change="handleBlockSelection"
                    title="$t('content_builder_add_block')"
                >
                    <option value="">
                        {{ $t('content_builder_select_block') }}
                    </option>
                    <option
                        v-for="block in availableBlocks"
                        :key="block.id"
                        :value="block.id"
                    >
                        {{ block.name }}
                    </option>
                </select>

            </div>
        </div>

        <div v-if="error" class="content-builder-error">
            {{ error }}
        </div>

        <div v-if="blocks.length === 0" class="content-builder-empty">
            {{ $t('content_builder_empty') }}
        </div>

        <div v-else class="content-builder-list">
            <div
                v-for="(block, index) in blocks"
                :key="block.local_uid"
                class="content-builder-item"
                draggable="true"
                @dragstart="startDrag(block.local_uid)"
                @dragover.prevent
                @drop="dropBlock(block.local_uid)"
            >
                <div class="content-builder-item-main">
                    <div class="content-builder-item-title">
                        {{ getBlockLabel(block.block_id) }}
                    </div>
                    <div class="content-builder-item-summary">
                        <div
                            v-if="getSummaryImageUrls(block).length > 0"
                            class="content-builder-item-summary-images"
                        >
                            <img
                                v-for="(imageUrl, imageIndex) in getSummaryImageUrls(block)"
                                :key="`${block.local_uid}-summary-image-${imageIndex}`"
                                :src="imageUrl"
                                class="content-builder-item-summary-image"
                                alt=""
                            />
                        </div>
                        <span>
                            {{ block.summary || $t('content_builder_no_content') }}
                        </span>
                    </div>
                </div>

                <div class="item-actions">
                    <span @click="editBlock(block.local_uid)">
                        <Icon name="pencil" :size="16" />
                    </span>
                    <span @click="moveBlock(block.local_uid, -1)">
                        <Icon name="arrow-up" :size="16" />
                    </span>
                    <span @click="moveBlock(block.local_uid, 1)">
                        <Icon name="arrow-down" :size="16" />
                    </span>
                    <span @click="removeBlock(block.local_uid)">
                        <Icon name="trash-2" :size="16" />
                    </span>
                </div>
            </div>
        </div>

        <teleport to="body">
            <div
                v-if="editingBlock"
                class="content-builder-modal-overlay admin-page"
                @click="handleEditorOverlayClick"
            >
                <div class="content-builder-modal" @click.stop>
                    <div class="content-builder-modal-header">
                        <h4>{{ getBlockLabel(editingBlock.block_id) }}</h4>
                        <button
                            type="button"
                            class="content-builder-modal-close"
                            :aria-label="$t('content_builder_close_editor')"
                            @click="closeEditor"
                        >
                            ×
                        </button>
                    </div>

                    <div class="content-builder-modal-body">
                        <div
                            v-for="field in getBlockFields(editingBlock.block_id)"
                            :key="`${editingBlock.local_uid}-${field.key}`"
                            class="form-group"
                        >
                            <label :for="getNestedFieldId(field)">
                                {{ field.name }}
                                <span v-if="field.is_required" class="required">*</span>
                            </label>

                            <component
                                v-if="getFieldEditorComponent(field)"
                                :is="getFieldEditorComponent(field)"
                                :id="getNestedFieldId(field)"
                                :name="field.key"
                                :configuration="field.configuration || {}"
                                v-model="editingBlock.values[field.key]"
                            />

                            <div v-else class="field-info">
                                {{ $t('content_builder_unsupported_field_type') }}: {{ field.type }}
                            </div>
                            <p v-if="field.configuration?.note" class="field-note">
                                {{ field.configuration.note }}
                            </p>
                        </div>

                        <div
                            v-if="getBlockFields(editingBlock.block_id).length === 0"
                            class="content-builder-empty-fields"
                        >
                            {{ $t('content_builder_no_configurable_fields') }}
                        </div>
                    </div>

                    <div class="content-builder-modal-actions">
                        <button type="button" class="btn btn-primary" @click="saveEditingBlock">
                            {{ $t('content_builder_save_block') }}
                        </button>
                        <button type="button" class="btn btn-secondary" @click="closeEditor">
                            {{ $t('cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>

<script>
import axios from 'axios';
import { getApiPrefix } from '@reno-cms/api';
import { getMediaThumbnails } from '@reno-cms/api/media';
import { loadComponent } from '@reno-cms/utils/componentLoader';
import { getFieldEditorModulePath } from '@reno-cms/utils/fieldEditor';
import Icon from '@reno-cms/components/common/Icon';

export default {
    name: 'ContentBuilderEditor',
    components: {
        Icon,
    },
    props: {
        modelValue: {
            type: [Object, null],
            default: null,
        },
        configuration: {
            type: Object,
            default: () => ({}),
        },
        resourceId: {
            type: [Number, null],
            default: null,
        },
        resourceFieldId: {
            type: [Number, null],
            default: null,
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            selectedBlockId: '',
            draggingUid: null,
            editingUid: null,
            editingBlock: null,
            error: null,
            blocks: [],
            fetchingNow: false,
            blocksLoaded: false,
            mediaPreviewById: {},
        };
    },
    computed: {
        availableBlocks() {
            return Array.isArray(this.configuration.available_blocks)
                ? this.configuration.available_blocks
                : [];
        },
    },
    watch: {
        modelValue: {
            immediate: true,
            deep: true,
            async handler(value) {
                if (parseInt(value) || value === null || value === '') {
                    value = {
                        builder_id: this.configuration.builder_id
                    }
                    this.blocksLoaded = false;
                    this.syncFromModelValue(value);
                    this.emitValue();
                } else {
                    this.syncFromModelValue(value);
                }

                if (this.shouldFetchBlocks()) {
                    await this.fetchBlocks();
                }
            },
        },
    },
    async mounted() {
        window.addEventListener('keydown', this.handleEditorEscape);

        if (this.shouldFetchBlocks()) {
            await this.fetchBlocks();
        }
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.handleEditorEscape);
    },
    methods: {
        handleBlockSelection() {
            if (!this.selectedBlockId) {
                return;
            }

            this.addBlock();
        },
        syncFromModelValue(value) {
            this.blocks = (value.blocks || []).map((block, index) => this.normalizeBlock(block, index));
            this.preloadSummaryMedia(this.blocks);
        },
        shouldFetchBlocks() {
            return Boolean(
                this.resourceId
                && this.resourceFieldId
                && this.configuration.builder_id
                && this.blocks.length === 0
                && !this.fetchingNow
                && !this.blocksLoaded
            );
        },
        normalizeBlock(block, index) {
            const summaryPreview = this.buildSummaryPreview(block?.values, block?.block_id);

            return {
                id: block?.id ?? null,
                block_id: block?.block_id,
                values: block?.values && typeof block.values === 'object' ? { ...block.values } : {},
                summary: summaryPreview.text,
                summary_preview: summaryPreview,
                local_uid: block?.local_uid || `block-${Date.now()}-${index}-${Math.random().toString(16).slice(2)}`,
            };
        },
        async fetchBlocks() {
            if (!this.resourceId || !this.resourceFieldId) {
                return;
            }

            this.fetchingNow = true;

            try {
                const response = await axios.get(`${getApiPrefix()}/content-builder`, {
                    params: {
                        resource_id: this.resourceId,
                        resource_field_id: this.resourceFieldId,
                    },
                });

                this.blocks = (Array.isArray(response.data?.data) ? response.data.data : []).map((block, index) => this.normalizeBlock(block, index));
                this.blocksLoaded = true;
                this.preloadSummaryMedia(this.blocks);
                this.emitValue();
            } catch (error) {
                console.error('Error loading content builder blocks:', error);
            }

            this.fetchingNow = false;
        },
        addBlock() {
            const blockId = this.selectedBlockId;
            const definition = this.getBlockDefinition(blockId);
            if (!definition) {
                return;
            }

            const block = this.normalizeBlock({
                block_id: blockId,
                values: this.getDefaultValues(definition),
            }, this.blocks.length);

            this.blocks.push(block);
            this.selectedBlockId = '';
            this.openEditor(block.local_uid);
            this.emitValue();
        },
        editBlock(localUid) {
            this.openEditor(localUid);
        },
        openEditor(localUid) {
            const block = this.blocks.find((item) => item.local_uid === localUid);
            if (!block) {
                return;
            }

            this.editingUid = localUid;
            this.editingBlock = {
                ...block,
                values: { ...block.values },
            };
            this.error = null;
        },
        closeEditor() {
            this.editingUid = null;
            this.editingBlock = null;
            this.error = null;
        },
        handleEditorOverlayClick(event) {
            if (event.target === event.currentTarget) {
                this.closeEditor();
            }
        },
        handleEditorEscape(event) {
            if (event.key === 'Escape' && this.editingBlock) {
                this.closeEditor();
            }
        },
        saveEditingBlock() {
            if (!this.editingBlock || !this.editingUid) {
                return;
            }

            const blockIndex = this.blocks.findIndex((item) => item.local_uid === this.editingUid);
            if (blockIndex === -1) {
                return;
            }

            const summaryPreview = this.buildSummaryPreview(this.editingBlock.values, this.editingBlock.block_id);

            this.blocks.splice(blockIndex, 1, {
                ...this.editingBlock,
                summary: summaryPreview.text,
                summary_preview: summaryPreview,
            });
            this.preloadSummaryMedia(this.blocks);
            this.closeEditor();
            this.emitValue();
        },
        removeBlock(localUid) {
            this.blocks = this.blocks.filter((block) => block.local_uid !== localUid);
            if (this.editingUid === localUid) {
                this.closeEditor();
            }
            this.emitValue();
        },
        moveBlock(localUid, direction) {
            const currentIndex = this.blocks.findIndex((block) => block.local_uid === localUid);
            const targetIndex = currentIndex + direction;

            if (currentIndex === -1 || targetIndex < 0 || targetIndex >= this.blocks.length) {
                return;
            }

            const blocks = [...this.blocks];
            const [movedBlock] = blocks.splice(currentIndex, 1);
            blocks.splice(targetIndex, 0, movedBlock);
            this.blocks = blocks;
            this.normalizeSortOrder();
            this.emitValue();
        },
        startDrag(localUid) {
            this.draggingUid = localUid;
        },
        dropBlock(targetUid) {
            if (!this.draggingUid || this.draggingUid === targetUid) {
                return;
            }

            const blocks = [...this.blocks];
            const sourceIndex = blocks.findIndex((block) => block.local_uid === this.draggingUid);
            const targetIndex = blocks.findIndex((block) => block.local_uid === targetUid);

            if (sourceIndex === -1 || targetIndex === -1) {
                this.draggingUid = null;
                return;
            }

            const [draggedBlock] = blocks.splice(sourceIndex, 1);
            blocks.splice(targetIndex, 0, draggedBlock);
            this.blocks = blocks;
            this.draggingUid = null;
            this.emitValue();
        },
        emitValue() {
            this.$emit('update:modelValue', {
                builder_id: this.configuration.builder_id,
                blocks: this.blocks.map((block) => ({
                    id: block.id,
                    block_id: block.block_id,
                    local_uid: block.local_uid,
                    values: block.values,
                })),
            });
        },
        getBlockDefinition(blockId) {
            return this.availableBlocks.find((block) => block.id === blockId) || null;
        },
        getBlockFields(blockId) {
            return this.getBlockDefinition(blockId)?.fields || [];
        },
        getBlockLabel(blockId) {
            return this.getBlockDefinition(blockId)?.name || this.$t('content_builder_block');
        },
        getDefaultValues(definition) {
            const values = {};

            (definition.fields || []).forEach((field) => {
                const cfg = field.configuration || {};
                if (Object.prototype.hasOwnProperty.call(cfg, 'default')) {
                    values[field.key] = cfg.default;
                } else {
                    values[field.key] = null;
                }
            });

            return values;
        },
        getFieldEditorComponent(field) {
            const jsModule = getFieldEditorModulePath(field);
            if (!jsModule) {
                return null;
            }

            return loadComponent(jsModule, {
                errorMessage: this.$t('content_builder_field_component_not_found'),
                loadingMessage: this.$t('content_builder_loading_field_component'),
            });
        },
        getNestedFieldId(field) {
            return `content-builder-${this.editingUid}-${field.key}`;
        },
        buildSummaryPreview(values, blockId) {
            const summaryTokens = {
                textParts: [],
                imageIds: [],
                imageUrls: [],
            };
            const mediaFieldPatterns = this.getMediaFieldPatterns(blockId);

            this.collectSummaryTokens(values, '', summaryTokens, mediaFieldPatterns);

            const text = summaryTokens.textParts
                .slice(0, 3)
                .map((value) => value.slice(0, 80))
                .join(' · ');

            return {
                text: text || this.$t('content_builder_block_no_content'),
                imageIds: [...new Set(summaryTokens.imageIds)].slice(0, 3),
                imageUrls: [...new Set(summaryTokens.imageUrls)].slice(0, 3),
            };
        },
        collectSummaryTokens(value, path, summaryTokens, mediaFieldPatterns) {
            if (value === null || value === undefined) {
                return;
            }

            if (this.isMediaObject(value)) {
                if (Number.isFinite(Number(value.id))) {
                    summaryTokens.imageIds.push(Number(value.id));
                }
                if (typeof value.url === 'string' && value.url.trim() !== '') {
                    summaryTokens.imageUrls.push(value.url.trim());
                }
                return;
            }

            if (Array.isArray(value)) {
                value.forEach((item, index) => {
                    const nextPath = path ? `${path}.${index}` : String(index);
                    this.collectSummaryTokens(item, nextPath, summaryTokens, mediaFieldPatterns);
                });
                return;
            }

            if (typeof value === 'object') {
                Object.entries(value).forEach(([key, nestedValue]) => {
                    const nextPath = path ? `${path}.${key}` : key;
                    this.collectSummaryTokens(nestedValue, nextPath, summaryTokens, mediaFieldPatterns);
                });
                return;
            }

            if (typeof value === 'string') {
                const trimmedValue = value.trim();
                if (!trimmedValue) {
                    return;
                }

                if (this.isMediaPath(path, mediaFieldPatterns) && this.looksLikeImageUrl(trimmedValue)) {
                    summaryTokens.imageUrls.push(trimmedValue);
                    return;
                }

                summaryTokens.textParts.push(trimmedValue);
                return;
            }

            if (typeof value === 'number') {
                if (this.isMediaPath(path, mediaFieldPatterns)) {
                    summaryTokens.imageIds.push(value);
                    return;
                }

                summaryTokens.textParts.push(String(value));
            }
        },
        getMediaFieldPatterns(blockId) {
            const fields = this.getBlockFields(blockId);
            return this.collectMediaFieldPatterns(fields);
        },
        collectMediaFieldPatterns(fields, prefix = '') {
            if (!Array.isArray(fields)) {
                return [];
            }

            const patterns = [];

            fields.forEach((field) => {
                if (!field?.key) {
                    return;
                }

                const fieldPath = prefix ? `${prefix}.${field.key}` : field.key;
                const fieldType = String(field.type || '').toLowerCase();

                if (fieldType === 'media') {
                    patterns.push(fieldPath);
                }

                if (fieldType === 'repeater') {
                    const nestedSchema = field.configuration?.schema;
                    if (Array.isArray(nestedSchema)) {
                        patterns.push(...this.collectMediaFieldPatterns(nestedSchema, `${fieldPath}.*`));
                    }
                }
            });

            return patterns;
        },
        isMediaPath(path, mediaFieldPatterns) {
            if (!path || !Array.isArray(mediaFieldPatterns) || mediaFieldPatterns.length === 0) {
                return false;
            }

            return mediaFieldPatterns.some((pattern) => {
                const patternRegexp = `^${pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\\\*/g, '[^.]+')}$`;
                return new RegExp(patternRegexp).test(path);
            });
        },
        isMediaObject(value) {
            if (!value || typeof value !== 'object') {
                return false;
            }

            const hasUrl = typeof value.url === 'string' && value.url.trim() !== '';
            const mimeType = typeof value.mime_type === 'string' ? value.mime_type.toLowerCase() : '';
            return hasUrl && (mimeType.startsWith('image/') || this.looksLikeImageUrl(value.url));
        },
        looksLikeImageUrl(value) {
            if (typeof value !== 'string') {
                return false;
            }

            return /\.(png|jpe?g|gif|webp|svg)(\?.*)?$/i.test(value.trim());
        },
        getSummaryImageUrls(block) {
            const summaryPreview = block?.summary_preview;
            if (!summaryPreview) {
                return [];
            }

            const imageUrls = [];
            (summaryPreview.imageUrls || []).forEach((url) => {
                if (typeof url === 'string' && url.trim() !== '') {
                    imageUrls.push(url.trim());
                }
            });

            (summaryPreview.imageIds || []).forEach((id) => {
                const resolvedUrl = this.mediaPreviewById[id];
                if (resolvedUrl) {
                    imageUrls.push(resolvedUrl);
                }
            });

            return [...new Set(imageUrls)].slice(0, 3);
        },
        async preloadSummaryMedia(blocks) {
            const mediaIds = (blocks || [])
                .flatMap((block) => block?.summary_preview?.imageIds || [])
                .map((id) => Number(id))
                .filter((id) => Number.isFinite(id) && !this.mediaPreviewById[id]);

            if (mediaIds.length === 0) {
                return;
            }

            const uniqueMediaIds = [...new Set(mediaIds)];
            try {
                const response = await getMediaThumbnails(uniqueMediaIds, {
                    width: 80,
                    height: 80,
                    options: 'zc=1',
                });
                const previews = response?.data && typeof response.data === 'object' ? response.data : {};

                uniqueMediaIds.forEach((mediaId) => {
                    const mediaUrl = previews[String(mediaId)];
                    if (typeof mediaUrl === 'string' && mediaUrl.trim() !== '') {
                        this.mediaPreviewById[mediaId] = mediaUrl;
                    }
                });
            } catch (error) {
                console.error('Error loading media previews:', error);
            }
        },
    },
};
</script>

<style scoped>
.content-builder-editor {
    border: 1px solid #d9d9d9;
    border-radius: 6px;
    padding: 1rem;
    background: #fff;
}

.content-builder-toolbar,
.content-builder-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.content-builder-toolbar {
    margin-top: 0;
}

.content-builder-actions {
    display: flex;
    gap: 0.5rem;
}

.content-builder-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1rem;
}

.content-builder-item {
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 0.75rem 1rem;
}

.content-builder-item-main {
    flex: 1;
    min-width: 0;
}

.content-builder-item-title {
    font-weight: 600;
}

.content-builder-item-summary,
.content-builder-hint {
    color: #666;
    font-size: 0.9rem;
}

.content-builder-item-summary {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.content-builder-item-summary-images {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    flex-shrink: 0;
}

.content-builder-item-summary-image {
    width: 20px;
    height: 20px;
    border-radius: 3px;
    object-fit: cover;
    border: 1px solid #ddd;
}

.content-builder-empty,
.content-builder-error {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 6px;
}

.content-builder-empty {
    background: #f8f8f8;
    color: #666;
}

.content-builder-error {
    background: #fff1f1;
    color: #a40000;
}

.content-builder-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background: rgba(15, 23, 42, 0.45);
}

.content-builder-modal {
    width: min(100%, 900px);
    max-height: calc(100vh - 3rem);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
}

.content-builder-modal-header,
.content-builder-modal-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
}

.content-builder-modal-header {
    border-bottom: 1px solid #e5e5e5;
}

.content-builder-modal-header h4 {
    margin: 0;
}

.content-builder-modal-close {
    width: 2rem;
    height: 2rem;
    border: 0;
    border-radius: 4px;
    background: transparent;
    color: #666;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
}

.content-builder-modal-close:hover {
    background: #f4f4f5;
}

.content-builder-modal-body {
    overflow-y: auto;
    padding: 1.25rem;
}

.content-builder-modal-actions {
    justify-content: flex-end;
    border-top: 1px solid #e5e5e5;
}

.content-builder-empty-fields {
    color: #666;
}

.field-note {
    margin: 0.35rem 0 0;
    font-size: 0.8125rem;
    line-height: 1.35;
    color: #6b7280;
}
</style>
