<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, home, login, register } from '@/routes';
</script>

<template>
    <Head title="Welcome to Lexi" />

    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header
            class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6"
        >
            <Link
                :href="home()"
                class="rounded-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <AppLogo />
            </Link>

            <nav class="flex items-center gap-2 text-sm">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="rounded-md px-4 py-2 font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    Open chat
                </Link>
                <template v-else>
                    <Link
                        :href="login()"
                        class="rounded-md px-4 py-2 font-medium text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        Log in
                    </Link>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-6 py-20 text-center"
        >
            <div
                class="mb-8 flex size-16 items-center justify-center rounded-2xl border border-border bg-card shadow-sm"
                aria-hidden="true"
            >
                <AppLogoIcon class="size-9 text-foreground" />
            </div>

            <p
                class="mb-4 text-sm font-medium tracking-wide text-muted-foreground"
            >
                Your space to think out loud
            </p>
            <h1
                class="max-w-3xl text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl"
            >
                A little help with whatever's on your mind.
            </h1>
            <p
                class="mt-5 max-w-xl text-base leading-7 text-balance text-muted-foreground sm:text-lg"
            >
                Ask a question, explore an idea, or work through a draft. Lexi
                helps you find clarity, one conversation at a time.
            </p>

            <div class="mt-9 flex flex-col items-center gap-3 sm:flex-row">
                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    Start a conversation
                </Link>
                <Link
                    v-else
                    :href="register()"
                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    Start a conversation
                </Link>
                <Link
                    v-if="!$page.props.auth.user"
                    :href="login()"
                    class="inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-background px-6 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    I already have an account
                </Link>
            </div>
        </main>
    </div>
</template>
