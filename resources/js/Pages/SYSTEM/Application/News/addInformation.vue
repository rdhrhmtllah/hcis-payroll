<template>
    <div class="quill-markdown-editor">
        <h2>Editor Konten</h2>

        <div class="input-group">
            <label for="title">Judul:</label>
            <input
                type="text"
                id="title"
                v-model="contentTitle"
                placeholder="Masukkan judul di sini..."
            />
        </div>

        <div class="input-group">
            <label>Isi Konten:</label>
            <div ref="editor"></div>
        </div>

        <br />

        <button @click="saveContent">Simpan Konten (Judul & Markdown)</button>
    </div>
</template>

<script>
import Quill from "quill";
import "quill/dist/quill.snow.css"; // Gaya dasar untuk Quill
import TurndownService from "turndown"; // Impor Turndown

import { marked } from "marked"; // Untuk konversi Markdown ke HTML
import DOMPurify from "dompurify"; // Untuk sanitasi HTML (SANGAT PENTING!)

export default {
    name: "QuillMarkdownEditor",
    data() {
        return {
            editor: null, // Instance Quill editor
            contentTitle: "", // Tambahkan data untuk judul
            quillHtml: "", // HTML mentah dari Quill
            markdownOutput: "", // Markdown yang dihasilkan
            htmlFromMarkdown: "", // HTML yang dihasilkan dari Markdown (untuk pratinjau)
        };
    },
    mounted() {
        // Inisialisasi Quill editor
        this.editor = new Quill(this.$refs.editor, {
            theme: "snow",
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ["bold", "italic", "underline", "strike"],
                    ["blockquote", "code-block"],
                    [{ list: "ordered" }, { list: "bullet" }],
                    // Tambahkan format lain sesuai kebutuhan Anda
                ],
            },
        });

        // Dengarkan perubahan pada editor Quill
        this.editor.on("text-change", () => {
            this.quillHtml = this.editor.root.innerHTML;
            this.convertToMarkdown(); // Konversi otomatis saat teks berubah
        });

        // Opsional: Muat konten yang sudah ada dari backend saat pertama kali dimuat
        this.loadContentFromBackend();
    },
    methods: {
        convertToMarkdown() {
            const turndownService = new TurndownService({
                headingStyle: "atx",
                bulletListMarker: "-",
            });
            this.markdownOutput = turndownService.turndown(this.quillHtml);
            this.renderMarkdownPreview();
        },

        renderMarkdownPreview() {
            const rawHtml = marked.parse(this.markdownOutput);
            this.htmlFromMarkdown = DOMPurify.sanitize(rawHtml);
        },

        async saveContent() {
            if (!this.contentTitle.trim()) {
                alert("Judul tidak boleh kosong.");
                return;
            }
            if (!this.markdownOutput.trim()) {
                alert("Isi konten tidak boleh kosong.");
                return;
            }

            try {
                this.convertToMarkdown();
                const response = await fetch("/news/store", {
                    // Endpoint Laravel Anda
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                    body: JSON.stringify({
                        title: this.contentTitle, // Kirim judul
                        markdown_content: this.markdownOutput, // Kirim markdown
                    }),
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(
                        errorData.message || "Gagal menyimpan konten"
                    );
                }

                const data = await response.json();
                alert(data.message);
                console.log("Respons dari Laravel:", data);

                // Opsional: Reset form setelah berhasil disimpan
                // this.contentTitle = '';
                // this.editor.setText(''); // Mengosongkan editor Quill
                // this.markdownOutput = '';
                // this.htmlFromMarkdown = '';
            } catch (error) {
                console.error("Ada kesalahan saat menyimpan konten:", error);
                alert("Gagal menyimpan konten: " + error.message);
            }
        },

        // async loadContentFromBackend() {
        //     try {
        //         const response = await fetch("/api/get-content"); // Endpoint untuk mengambil konten
        //         if (response.ok) {
        //             const data = await response.json();
        //             if (data.title || data.markdown_content) {
        //                 // Cek jika ada data
        //                 this.contentTitle = data.title || "";
        //                 this.markdownOutput = data.markdown_content || "";

        //                 // Jika ada markdown_content, konversi dan set ke Quill & pratinjau
        //                 if (this.markdownOutput) {
        //                     const htmlForQuill = DOMPurify.sanitize(
        //                         marked.parse(this.markdownOutput)
        //                     );
        //                     this.editor.root.innerHTML = htmlForQuill;
        //                     this.quillHtml = this.editor.root.innerHTML; // Update quillHtml
        //                 } else {
        //                     this.editor.setText(""); // Pastikan Quill kosong jika tidak ada konten
        //                     this.quillHtml = "";
        //                 }
        //                 this.renderMarkdownPreview();
        //             }
        //         } else {
        //             console.log("Belum ada konten tersimpan.");
        //             // Kosongkan Quill dan input judul jika tidak ada data
        //             this.contentTitle = "";
        //             this.editor.setText("");
        //             this.quillHtml = "";
        //             this.markdownOutput = "";
        //             this.htmlFromMarkdown = "";
        //         }
        //     } catch (error) {
        //         console.error("Gagal memuat konten awal:", error);
        //         // Kosongkan form jika terjadi error saat memuat
        //         this.contentTitle = "";
        //         this.editor.setText("");
        //         this.quillHtml = "";
        //         this.markdownOutput = "";
        //         this.htmlFromMarkdown = "";
        //     }
        // },
    },
};
</script>

<style scoped>
.quill-markdown-editor {
    display: flex;
    flex-direction: column;
    gap: 20px;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.input-group label {
    font-weight: bold;
    color: #555;
}

input[type="text"] {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 16px;
}

::v-deep .ql-container {
    min-height: 250px;
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 4px;
}

::v-deep .ql-toolbar {
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
}

textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-family: monospace;
    font-size: 16px;
    resize: vertical;
    background-color: #f0f0f0;
}

.html-preview {
    border: 1px dashed #eee;
    padding: 15px;
    background-color: #f9f9f9;
    min-height: 100px;
    border-radius: 4px;
}

.html-preview h1,
.html-preview h2,
.html-preview h3 {
    color: #333;
    margin-top: 1em;
    margin-bottom: 0.5em;
}

.html-preview p {
    line-height: 1.6;
    margin-bottom: 1em;
}

.html-preview ul {
    margin-left: 20px;
    list-style-type: disc;
}

.html-preview b,
.html-preview strong {
    font-weight: bold;
}

.html-preview i,
.html-preview em {
    font-style: italic;
}

button {
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    align-self: flex-end;
}

button:hover {
    background-color: #0056b3;
}
</style>
