<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { NativeSelect } from '@/components/ui/native-select';
import CodeViewer from './components/CodeViewer.vue';
import MaxLengthSelector from './components/MaxLengthSelector.vue';
import PresetActions from './components/PresetActions.vue';
import PresetSave from './components/PresetSave.vue';
import PresetShare from './components/PresetShare.vue';
import TemperatureSelector from './components/TemperatureSelector.vue';
import TopPSelector from './components/TopPSelector.vue';
import { models } from './data/models';
import { presets } from './data/presets';

const { t } = useI18n();

const preset = ref(presets[0].id);
const model = ref(models[0].id);
const input = ref('');
</script>

<template>
    <div class="h-full flex-col md:flex">
        <header class="flex flex-col items-start justify-between gap-4 px-6 py-4 sm:flex-row sm:items-center sm:space-y-0 md:h-16">
            <h1 class="text-lg font-semibold">{{ t('examples.shells.playground.title') }}</h1>
            <div class="ml-auto flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <div class="grid gap-1.5">
                    <Label class="sr-only" for="playground-preset">{{ t('examples.shells.playground.preset') }}</Label>
                    <NativeSelect id="playground-preset" v-model="preset" class="w-[220px]">
                        <option v-for="item in presets" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </NativeSelect>
                </div>
                <PresetSave />
                <div class="hidden space-x-2 md:flex">
                    <CodeViewer />
                    <PresetShare />
                </div>
                <PresetActions />
            </div>
        </header>

        <Separator />

        <Tabs default-value="complete" class="flex-1">
            <main id="content" class="container h-full py-6">
                <div class="grid h-full items-stretch gap-6 md:grid-cols-[1fr_260px]">
                    <div class="order-2 flex flex-col space-y-4 md:order-1">
                        <div class="grid gap-2">
                            <p id="playground-mode" class="text-sm font-medium">{{ t('examples.shells.playground.mode') }}</p>
                            <TabsList aria-labelledby="playground-mode" class="grid grid-cols-3">
                                <TabsTrigger value="complete">{{ t('examples.shells.playground.modes.complete') }}</TabsTrigger>
                                <TabsTrigger value="insert">{{ t('examples.shells.playground.modes.insert') }}</TabsTrigger>
                                <TabsTrigger value="edit">{{ t('examples.shells.playground.modes.edit') }}</TabsTrigger>
                            </TabsList>
                        </div>

                        <div class="grid gap-2">
                            <Label for="playground-model">{{ t('examples.shells.playground.model') }}</Label>
                            <NativeSelect id="playground-model" v-model="model">
                                <option v-for="item in models" :key="item.id" :value="item.id">{{ item.name }}</option>
                            </NativeSelect>
                        </div>

                        <TemperatureSelector :default-value="[0.56]" />
                        <MaxLengthSelector :default-value="[256]" />
                        <TopPSelector :default-value="[0.9]" />
                    </div>

                    <div class="order-1 md:order-2 md:col-start-1 md:row-start-1">
                        <TabsContent value="complete" class="mt-0 border-0 p-0">
                            <div class="flex h-full flex-col space-y-4">
                                <Label class="sr-only" for="playground-input">
                                    {{ t('examples.shells.playground.promptLabel') }}
                                </Label>
                                <Textarea
                                    id="playground-input"
                                    v-model="input"
                                    :placeholder="t('examples.shells.playground.promptPlaceholder')"
                                    class="min-h-[400px] flex-1 p-4 md:min-h-[600px] lg:min-h-[500px]"
                                />
                                <div class="flex items-center space-x-2">
                                    <Button>{{ t('examples.shells.playground.submit') }}</Button>
                                    <Button variant="secondary">{{ t('examples.shells.playground.history') }}</Button>
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="insert" class="mt-0 border-0 p-0">
                            <p class="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                {{ t('examples.shells.playground.modeHint') }}
                            </p>
                        </TabsContent>

                        <TabsContent value="edit" class="mt-0 border-0 p-0">
                            <p class="rounded-md border border-dashed p-6 text-sm text-muted-foreground">
                                {{ t('examples.shells.playground.modeHint') }}
                            </p>
                        </TabsContent>
                    </div>
                </div>
            </main>
        </Tabs>
    </div>
</template>
