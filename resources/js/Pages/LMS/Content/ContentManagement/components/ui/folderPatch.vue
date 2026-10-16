<template>
    <div class="folder-card stitch-card mb-0 h-100">
        <div class="folder-icon-wrapper">
            <svg width="44" height="36" viewBox="0 0 140 110" fill="none" class="folder-svg">
                <path
                    d="M125 105H15C9.47715 105 5 100.523 5 95V35C5 29.4772 9.47715 25 15 25H45L55 10H125C130.523 10 135 14.4772 135 20V95C135 100.523 130.523 105 125 105Z"
                    fill="#4f46e5"
                />
                <path
                    d="M125 105H15C9.47715 105 5 100.523 5 95V45C5 39.4772 9.47715 35 15 35H125C130.523 35 135 39.4772 135 45V95C135 100.523 130.523 105 125 105Z"
                    fill="#818cf8"
                />
            </svg>
        </div>

        <div class="folder-footer">
            <div class="folder-info">
                <h3 class="folder-title" :title="folderName">{{ folderName }}</h3>
                <p class="folder-meta">
                    {{ fileCount }} Files <span>•</span>
                    <span v-tooltip="created_by">{{ truncateText(created_by, 20) }}</span>
                </p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FolderCard',
    props: {
        folderName: {
            type: String,
            required: true,
            default: 'New Folder',
        },
        fileCount: {
            type: Number,
            default: 0,
        },
        totalSize: {
            type: String,
            default: '0 MB',
        },
        created_by: {
            type: String,
            default: '',
        },
        members: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            maxVisible: 3, // Jumlah foto profil maksimal yang muncul
        };
    },
    methods: {
        truncateText(text, maxLength) {
            if (text.length <= maxLength) {
                return text;
            }
            return text.substring(0, maxLength - 3) + '...';
        },
    },
    computed: {
        visibleMembers() {
            return this.members.slice(0, this.maxVisible);
        },
        extraCount() {
            return this.members.length > this.maxVisible ? this.members.length - this.maxVisible : 0;
        },
    },
    mounted() {
        // console.log('Komponen FolderCard siap digunakan!');
    },
};
</script>
<style scoped>
/* Styling khusus untuk tumpukan foto profil */
.avatar-group img,
.avatar-group .more-badge {
    border: 2px solid white;
}
.avatar-group img:not(:first-child),
.avatar-group .more-badge {
    margin-left: -10px;
}

/* CUSTOM CSS UNTUK KOMPONEN (Tanpa Tailwind) */
.folder-card {
    padding: 16px 18px;
    width: 100%;
    cursor: pointer;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05), 0 2px 8px -1px rgba(0, 0, 0, 0.03);
    border-radius: 16px;
    border: 1px solid var(--color-border, var(--hcis-slate-200));
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background-color: var(--hcis-white);
}

.folder-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
    border-color: var(--color-primary, var(--hcis-blue-500));
}

/* Ikon Wrapper */
.folder-icon-wrapper {
    background: linear-gradient(135deg, var(--hcis-violet-50) 0%, #ede9fe 100%);
    border-radius: 12px;
    aspect-ratio: 2 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.folder-card:hover .folder-icon-wrapper {
    background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
}

.folder-card:hover .folder-svg {
    transform: scale(1.08);
}

.folder-svg {
    filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.05));
    transition: transform 0.3s ease;
}

/* Info Section */
.folder-footer {
    display: flex;
    align-items: center;
}

.folder-info {
    overflow: hidden;
    width: 100%;
}

.folder-title {
    color: var(--hcis-slate-900);
    font-size: 0.88rem;
    font-weight: 600;
    margin: 0 0 4px 0;
    letter-spacing: -0.01em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.folder-meta {
    color: var(--hcis-slate-500);
    font-size: 0.72rem;
    font-weight: 500;
    margin: 0;
}

.folder-meta span {
    margin: 0 4px;
    opacity: 0.5;
}

/* Avatar Stack */
.avatar-group {
    display: flex;
    align-items: center;
}

.avatar-img,
.more-badge {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1.5px solid white;
    object-fit: cover;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.avatar-img:not(:first-child),
.more-badge {
    margin-left: -10px;
}

.more-badge {
    background-color: #1d8cf8;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    text-decoration: none;
}
</style>
