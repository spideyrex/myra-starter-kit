<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import UserAuthForm from './components/UserAuthForm.vue';

const { t } = useI18n();
</script>

<template>
    <div class="container relative grid min-h-svh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="absolute right-4 top-4 md:right-8 md:top-8">
            <Button variant="ghost">{{ t('examples.shells.authentication.login') }}</Button>
        </div>

        <div class="relative hidden h-full flex-col bg-muted p-10 text-white dark:border-r lg:flex">
            <div class="absolute inset-0 bg-zinc-900" aria-hidden="true" />
            <div class="relative z-20 flex items-center text-lg font-medium">
                {{ t('examples.shells.authentication.brand') }}
            </div>
            <div class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <p class="text-lg">&ldquo;{{ t('examples.shells.authentication.quote') }}&rdquo;</p>
                    <footer class="text-sm">{{ t('examples.shells.authentication.quoteAuthor') }}</footer>
                </blockquote>
            </div>
        </div>

        <main id="content" class="lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ t('examples.shells.authentication.title') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ t('examples.shells.authentication.subtitle') }}
                    </p>
                </div>

                <UserAuthForm />

                <p class="px-8 text-center text-sm text-muted-foreground">
                    {{ t('examples.shells.authentication.terms') }}
                </p>
            </div>
        </main>
    </div>
</template>
