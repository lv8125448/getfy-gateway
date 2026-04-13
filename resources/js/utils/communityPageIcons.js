/**
 * Ícones Lucide disponíveis para páginas da comunidade.
 * Usado no Member Builder (seletor) e na área de membros (exibição).
 */
import {
    MessageSquare,
    Megaphone,
    HelpCircle,
    Pin,
    PartyPopper,
    Lightbulb,
    FileText,
    Bell,
    Hand,
    Heart,
    Target,
    Calendar,
    FolderOpen,
    Mail,
    Trophy,
    Star,
    Flame,
    MessageCircle,
    Sparkles,
    CheckCircle,
    Trash2,
    Camera,
    Clapperboard,
    Mic,
    Headphones,
    Briefcase,
    BarChart3,
    Lock,
    Globe,
    Smartphone,
    Users,
    BookOpen,
    GraduationCap,
    Award,
    Gift,
    Zap,
    Bookmark,
    Share2,
    Send,
    Home,
    Search,
    Settings,
    Image,
    Video,
    Music,
    File,
    Folder,
    Archive,
    Inbox,
    Flag,
    AlertCircle,
    Info,
} from 'lucide-vue-next';

export const communityPageIconComponents = {
    MessageSquare,
    Megaphone,
    HelpCircle,
    Pin,
    PartyPopper,
    Lightbulb,
    FileText,
    Bell,
    Hand,
    Heart,
    Target,
    Calendar,
    FolderOpen,
    Mail,
    Trophy,
    Star,
    Flame,
    MessageCircle,
    Sparkles,
    CheckCircle,
    Trash2,
    Camera,
    Clapperboard,
    Mic,
    Headphones,
    Briefcase,
    BarChart3,
    Lock,
    Globe,
    Smartphone,
    Users,
    BookOpen,
    GraduationCap,
    Award,
    Gift,
    Zap,
    Bookmark,
    Share2,
    Send,
    Home,
    Search,
    Settings,
    Image,
    Video,
    Music,
    File,
    Folder,
    Archive,
    Inbox,
    Flag,
    AlertCircle,
    Info,
};

export const communityPageIconNames = Object.keys(communityPageIconComponents);

/** Retorna o componente Vue para exibir o ícone, ou null se for emoji (texto). */
export function getCommunityPageIconComponent(iconValue) {
    if (!iconValue || typeof iconValue !== 'string') return null;
    if (!iconValue.startsWith('icon:')) return null;
    const name = iconValue.slice(5);
    return communityPageIconComponents[name] ?? null;
}

/** Verifica se o valor é um ícone Lucide (icon:Name). */
export function isIconName(iconValue) {
    return iconValue && typeof iconValue === 'string' && iconValue.startsWith('icon:');
}

/** Lista grande de emojis para o seletor da página da comunidade. */
export const communityPageEmojis = [
    '💬', '📢', '❓', '📌', '🎉', '💡', '📝', '🔔', '👋', '❤️',
    '🎯', '📅', '📂', '📧', '🏆', '⭐', '🔥', '📣', '🌟', '✅',
    '🗑️', '📷', '🎬', '🎤', '🎧', '💼', '📊', '🔒', '🌐', '📱',
    '👍', '👎', '🙌', '👏', '🤝', '💪', '🧠', '👀', '💭', '🗣️',
    '📖', '✏️', '📎', '🔖', '📋', '📁', '💾', '🖼️', '🎨', '🚀',
    '⚡', '🎁', '🏅', '🎖️', '🤔', '😊', '🎓', '📚', '🌍', '🔐',
    '⚙️', '🏠', '🛒', '💰', '📈', '📉', '🔍', '✉️', '📮', '🏷️',
    '🆕', '🆓', '✔️', '❌', '💯', '🎪', '🏆', '🥇', '🥈', '🥉',
    '📦', '🔑', '🛡️', '⚔️', '🏹', '🎯', '🧩', '🃏', '🎲', '🪄',
    '🌈', '☀️', '🌙', '⭐', '✨', '💫', '🔥', '💧', '🌱', '🍀',
];
