<script setup>
import { ref, watch } from 'vue';
import { useForm, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import Button from '@/components/ui/Button.vue';
import { MessageSquare, ImagePlus, X, Heart, MessageCircle, Trash2 } from 'lucide-vue-next';
import { getCommunityPageIconComponent } from '@/utils/communityPageIcons';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product: { type: Object, required: true },
    config: { type: Object, default: () => ({}) },
    auth_user_id: { type: Number, default: null },
    can_delete_any_post: { type: Boolean, default: false },
    community_users_can_delete_own_posts: { type: Boolean, default: true },
    pages: { type: Array, default: () => [] },
    page: { type: Object, required: true },
    posts: { type: Object, required: true },
    slug: { type: String, required: true },
});

const postsList = ref(props.posts?.data ? [...props.posts.data] : []);
watch(() => props.posts?.data, (data) => {
    postsList.value = data ? [...data] : [];
}, { immediate: true });

const postForm = useForm({ content: '', image: null });
const postImageInputRef = ref(null);
const postImagePreviewUrl = ref('');
const likeLoadingId = ref(null);
const commentLoadingId = ref(null);
const commentContentByPost = ref({});

const basePath = `/m/${props.slug}/comunidade`;
const postsBase = () => `${basePath}/${props.page.slug}/posts`;
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function formatPostDate(isoString) {
    if (!isoString) return '';
    const d = new Date(isoString);
    return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function getInitials(name) {
    if (!name) return 'A';
    return name.split(/\s+/).map((n) => n[0]).slice(0, 2).join('').toUpperCase() || 'A';
}
function canDeletePost(post) {
    if (props.can_delete_any_post) return true;
    if (post.user_id === props.auth_user_id && props.community_users_can_delete_own_posts) return true;
    return false;
}

function onPostImageChange(event) {
    const file = event.target?.files?.[0];
    if (!file) return;
    postForm.image = file;
    postImagePreviewUrl.value = URL.createObjectURL(file);
}
function clearPostImage() {
    postForm.image = null;
    postImagePreviewUrl.value = '';
    if (postImageInputRef.value) postImageInputRef.value.value = '';
}
function submitPost() {
    postForm.post(`/m/${props.slug}/comunidade/${props.page.slug}/posts`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            postForm.reset();
            clearPostImage();
        },
    });
}

function deletePost(post) {
    if (!confirm('Excluir esta postagem?')) return;
    router.delete(`${postsBase()}/${post.id}`, { preserveScroll: true });
}
async function toggleLike(post) {
    const idx = postsList.value.findIndex((p) => p.id === post.id);
    if (idx < 0) return;
    likeLoadingId.value = post.id;
    try {
        const url = `${postsBase()}/${post.id}/like`;
        if (post.user_has_liked) {
            const { data } = await axios.delete(url, { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
            postsList.value[idx] = { ...postsList.value[idx], likes_count: data.likes_count, user_has_liked: data.user_has_liked };
        } else {
            const { data } = await axios.post(url, {}, { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
            postsList.value[idx] = { ...postsList.value[idx], likes_count: data.likes_count, user_has_liked: data.user_has_liked };
        }
    } finally {
        likeLoadingId.value = null;
    }
}
function getCommentContent(postId) {
    return commentContentByPost.value[postId] ?? '';
}
function setCommentContent(postId, value) {
    commentContentByPost.value[postId] = value;
}
async function submitComment(post) {
    const content = (commentContentByPost.value[post.id] ?? '').trim();
    if (!content) return;
    const idx = postsList.value.findIndex((p) => p.id === post.id);
    if (idx < 0) return;
    commentLoadingId.value = post.id;
    try {
        const { data } = await axios.post(`${postsBase()}/${post.id}/comments`, { content }, { headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' } });
        const nextComments = [...(postsList.value[idx].comments ?? []), data.comment];
        postsList.value[idx] = { ...postsList.value[idx], comments: nextComments };
        setCommentContent(post.id, '');
    } finally {
        commentLoadingId.value = null;
    }
}
</script>

<template>
    <div class="flex flex-col gap-6 lg:flex-row lg:gap-8">
        <!-- Sidebar: lista de páginas -->
        <aside class="w-full shrink-0 rounded-2xl border border-zinc-700 bg-zinc-800/50 shadow-lg lg:w-72">
            <div class="border-b border-zinc-700 p-4">
                <Link :href="basePath" class="text-xs text-zinc-500 hover:text-[var(--ma-primary)]">← Comunidade</Link>
                <h2 class="mt-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-zinc-400">
                    <MessageSquare class="h-4 w-4" />
                    Páginas
                </h2>
            </div>
            <nav class="p-2">
                <Link
                    v-for="p in pages"
                    :key="p.id"
                    :href="`${basePath}/${p.slug}`"
                    :class="[
                        'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                        p.slug === page.slug
                            ? 'bg-[var(--ma-primary)]/20 text-[var(--ma-primary)]'
                            : 'text-zinc-300 hover:bg-zinc-700/50 hover:text-white',
                    ]"
                >
                    <template v-if="p.icon">
                        <component v-if="getCommunityPageIconComponent(p.icon)" :is="getCommunityPageIconComponent(p.icon)" class="h-5 w-5 shrink-0 text-[var(--ma-primary)]" />
                        <span v-else class="text-xl leading-none">{{ p.icon }}</span>
                    </template>
                    <img v-else-if="p.banner_url" :src="p.banner_url" :alt="p.title" class="h-8 w-10 shrink-0 rounded-lg object-cover" />
                    <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--ma-primary)]/20">
                        <MessageSquare class="h-4 w-4 text-[var(--ma-primary)]" />
                    </span>
                    <span class="truncate">{{ p.title }}</span>
                </Link>
            </nav>
        </aside>

        <!-- Conteúdo principal -->
        <main class="min-w-0 flex-1 space-y-6">
            <!-- Banner da página -->
            <div v-if="page.banner_url" class="relative h-40 w-full overflow-hidden rounded-2xl border border-zinc-700 bg-zinc-800">
                <img :src="page.banner_url" :alt="page.title" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-t from-zinc-900/80 to-transparent" />
                <div class="absolute bottom-4 left-4 flex items-center gap-2">
                    <template v-if="page.icon">
                        <component v-if="getCommunityPageIconComponent(page.icon)" :is="getCommunityPageIconComponent(page.icon)" class="h-8 w-8 shrink-0 text-white" />
                        <span v-else class="text-3xl">{{ page.icon }}</span>
                    </template>
                    <h1 class="text-2xl font-bold text-white">{{ page.title }}</h1>
                </div>
            </div>
            <div v-else class="flex items-center gap-3">
                <template v-if="page.icon">
                    <span v-if="getCommunityPageIconComponent(page.icon)" class="flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--ma-primary)]/20">
                        <component :is="getCommunityPageIconComponent(page.icon)" class="h-6 w-6 text-[var(--ma-primary)]" />
                    </span>
                    <span v-else class="flex h-12 w-12 items-center justify-center rounded-xl bg-[var(--ma-primary)]/20 text-2xl">{{ page.icon }}</span>
                </template>
                <h1 class="text-2xl font-bold text-white">{{ page.title }}</h1>
            </div>

            <form v-if="page.is_public_posting" class="rounded-2xl border border-zinc-700 bg-zinc-800/50 p-5 shadow-lg" @submit.prevent="submitPost">
                <textarea
                    v-model="postForm.content"
                    rows="3"
                    class="w-full rounded-xl border border-zinc-600 bg-zinc-800 px-4 py-3 text-white placeholder-zinc-500 focus:border-[var(--ma-primary)] focus:ring-1 focus:ring-[var(--ma-primary)]"
                    placeholder="Escreva um post..."
                    required
                />
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <input
                        ref="postImageInputRef"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="onPostImageChange"
                    />
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-600 text-zinc-200 transition hover:bg-zinc-500 focus:outline-none focus:ring-2 focus:ring-[var(--ma-primary)]"
                        title="Anexar imagem"
                        aria-label="Anexar imagem"
                        @click="postImageInputRef?.click()"
                    >
                        <ImagePlus class="h-5 w-5" />
                    </button>
                    <div v-if="postImagePreviewUrl" class="relative shrink-0">
                        <img :src="postImagePreviewUrl" alt="Preview" class="h-16 w-auto max-w-[120px] rounded-lg border border-zinc-600 object-cover" />
                        <button type="button" class="absolute -right-1 -top-1 rounded-full bg-zinc-700 p-1 text-zinc-200 hover:bg-zinc-600" @click="clearPostImage" aria-label="Remover imagem">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <Button type="submit" class="ml-auto" :disabled="postForm.processing">
                        {{ postForm.processing ? 'Publicando…' : 'Publicar' }}
                    </Button>
                </div>
                <p v-if="postForm.errors.content" class="mt-2 text-sm text-red-400">{{ postForm.errors.content }}</p>
                <p v-if="postForm.errors.image" class="mt-1 text-sm text-red-400">{{ postForm.errors.image }}</p>
            </form>
            <p v-else class="rounded-xl border border-zinc-700 bg-zinc-800/30 px-4 py-3 text-sm text-zinc-500">Apenas o instrutor pode publicar nesta página.</p>

            <div class="space-y-4">
                <article
                    v-for="post in postsList"
                    :key="post.id"
                    class="rounded-2xl border border-zinc-700 bg-zinc-800/50 p-5 shadow-lg"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[var(--ma-primary)]/20 text-sm font-semibold text-[var(--ma-primary)]">
                                <img v-if="post.user?.avatar_url" :src="post.user.avatar_url" :alt="post.user.name" class="h-full w-full object-cover" />
                                <span v-else>{{ getInitials(post.user?.name) }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-zinc-300">{{ post.user?.name }}</p>
                                <p class="text-xs text-zinc-500">{{ formatPostDate(post.created_at) }}</p>
                            </div>
                        </div>
                        <button
                            v-if="canDeletePost(post)"
                            type="button"
                            class="shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-zinc-700 hover:text-red-400"
                            title="Excluir postagem"
                            aria-label="Excluir postagem"
                            @click="deletePost(post)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap text-zinc-200">{{ post.content }}</p>
                    <img v-if="post.image_url" :src="post.image_url" alt="" class="mt-3 max-h-80 w-full rounded-xl object-cover object-center" />
                    <div class="mt-4 flex items-center gap-4 border-t border-zinc-700/50 pt-3">
                        <button
                            type="button"
                            :class="['flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm transition', post.user_has_liked ? 'text-red-400' : 'text-zinc-400 hover:text-zinc-300']"
                            :disabled="likeLoadingId === post.id"
                            @click="toggleLike(post)"
                        >
                            <Heart :class="['h-4 w-4', post.user_has_liked ? 'fill-current' : '']" />
                            <span>{{ post.likes_count ?? 0 }}</span>
                        </button>
                        <span class="flex items-center gap-1.5 text-sm text-zinc-500">
                            <MessageCircle class="h-4 w-4" />
                            {{ (post.comments ?? []).length }}
                        </span>
                    </div>
                    <div class="mt-3 space-y-2 border-t border-zinc-700/50 pt-3">
                        <div v-for="c in (post.comments ?? [])" :key="c.id" class="flex gap-2">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-zinc-600 text-xs font-medium text-zinc-200">
                                <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" :alt="c.user.name" class="h-full w-full object-cover" />
                                <span v-else>{{ getInitials(c.user?.name) }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-zinc-400">{{ c.user?.name }} · {{ c.created_at }}</p>
                                <p class="text-sm text-zinc-300">{{ c.content }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <input
                                :value="getCommentContent(post.id)"
                                type="text"
                                class="min-w-0 flex-1 rounded-lg border border-zinc-600 bg-zinc-800 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-[var(--ma-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--ma-primary)]"
                                placeholder="Escreva um comentário..."
                                @input="setCommentContent(post.id, $event.target.value)"
                                @keydown.enter.prevent="submitComment(post)"
                            />
                            <Button size="sm" :disabled="commentLoadingId === post.id || !getCommentContent(post.id)?.trim()" @click="submitComment(post)">
                                Comentar
                            </Button>
                        </div>
                    </div>
                </article>
            </div>
            <div v-if="posts.prev_page_url || posts.next_page_url" class="flex gap-2">
                <a
                    v-if="posts.prev_page_url"
                    :href="posts.prev_page_url"
                    class="rounded-xl border border-zinc-600 px-4 py-2 text-sm text-zinc-300 transition hover:bg-zinc-800"
                >
                    Anterior
                </a>
                <a
                    v-if="posts.next_page_url"
                    :href="posts.next_page_url"
                    class="rounded-xl border border-zinc-600 px-4 py-2 text-sm text-zinc-300 transition hover:bg-zinc-800"
                >
                    Próxima
                </a>
            </div>
        </main>
    </div>
</template>
