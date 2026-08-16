/**
 * Static accessibility scan of the vendored block templates.
 *
 * Parses the real SFC template AST (@vue/compiler-sfc, already a dependency of
 * vue) rather than mounting: the blocks render dozens of stubbed registry
 * components in jsdom, where almost no real DOM survives, so a mounted check
 * would be vacuous. The AST is the source of truth for what upstream wrote.
 *
 * Five rules, matching the bundle spec:
 *   R1 every <img> has alt, or alt="" plus aria-hidden
 *   R2 every <button>/[role=button] has an accessible name
 *   R3 no element carries tabindex > 0
 *   R4 every <input>/<select>/<textarea> is labelled
 *   R5 no <div>/<span> carries a click handler without role + tabindex="0"
 */

import fs from 'node:fs';
import path from 'node:path';
import { parse } from '@vue/compiler-sfc';

/**
 * R4 applies to things that ARE a form control: the native elements, and the
 * registry wrappers that render exactly one of them and forward attributes.
 * The composite <Select> is deliberately absent — it renders no control of its
 * own, its focusable element is <SelectTrigger>, and naming it there is correct.
 */
const NATIVE_CONTROLS = new Set(['input', 'select', 'textarea']);
const CONTROL_COMPONENTS = new Set(['Input', 'Textarea', 'NativeSelect', 'SidebarInput']);

const ELEMENT = 1;
const TEXT = 2;
const INTERPOLATION = 5;
const ATTRIBUTE = 6;
const DIRECTIVE = 7;

function attribute(node, name) {
    for (const prop of node.props ?? []) {
        if (prop.type === ATTRIBUTE && prop.name === name) return prop.value?.content ?? '';
        if (prop.type === DIRECTIVE && prop.name === 'bind' && prop.arg?.content === name) return '__bound__';
    }
    return null;
}

function hasClickHandler(node) {
    return (node.props ?? []).some(prop =>
        (prop.type === DIRECTIVE && prop.name === 'on' && prop.arg?.content === 'click')
        || (prop.type === ATTRIBUTE && prop.name === '@click'));
}

function hasText(node) {
    for (const child of node.children ?? []) {
        if (child.type === TEXT && child.content.trim() !== '') return true;
        if (child.type === INTERPOLATION) return true;
        if (child.type === ELEMENT && hasText(child)) return true;
    }
    return false;
}

function named(node) {
    return attribute(node, 'aria-label') !== null
        || attribute(node, 'aria-labelledby') !== null
        || attribute(node, 'title') !== null
        || hasText(node);
}

function walk(node, visit) {
    if (node.type === ELEMENT) visit(node);
    for (const child of node.children ?? []) {
        if (child && typeof child === 'object' && 'type' in child) walk(child, visit);
    }
}

/** @returns {string[]} rule ids this file breaks */
export function scanTemplate(source) {
    const { descriptor } = parse(source);
    const ast = descriptor.template?.ast;
    if (!ast) return [];

    const broken = new Set();
    const labelTargets = new Set();

    // <Label>, <FieldLabel> and <SidebarGroupLabel> all render a real <label for>.
    walk(ast, node => {
        if (/label$/.test((node.tag ?? '').toLowerCase())) {
            const target = attribute(node, 'for');
            if (target) labelTargets.add(target);
        }
    });

    walk(ast, node => {
        const tag = (node.tag ?? '').toLowerCase();
        const role = attribute(node, 'role');

        if (tag === 'img') {
            const alt = attribute(node, 'alt');
            const hidden = attribute(node, 'aria-hidden');
            if (alt === null && hidden !== 'true') broken.add('R1');
            if (alt === '' && hidden !== 'true') broken.add('R1');
        }

        if (tag === 'button' || role === 'button') {
            if (!named(node)) broken.add('R2');
        }

        const tabindex = attribute(node, 'tabindex');
        if (tabindex !== null && tabindex !== '__bound__' && Number(tabindex) > 0) broken.add('R3');

        if (NATIVE_CONTROLS.has(node.tag) || CONTROL_COMPONENTS.has(node.tag)) {
            const id = attribute(node, 'id');
            const labelled = (id !== null && id !== '__bound__' && labelTargets.has(id))
                || attribute(node, 'aria-label') !== null
                || attribute(node, 'aria-labelledby') !== null;
            if (!labelled) broken.add('R4');
        }

        if ((tag === 'div' || tag === 'span') && hasClickHandler(node)) {
            const interactive = role !== null && attribute(node, 'tabindex') === '0';
            if (!interactive) broken.add('R5');
        }
    });

    return [...broken].sort();
}

/** @returns {Record<string,string[]>} blockId -> broken rule ids (only blocks with findings) */
export function scanBlocks(blocksDir) {
    const out = {};

    for (const id of fs.readdirSync(blocksDir)) {
        const dir = path.join(blocksDir, id);
        if (id === '_unavailable' || !fs.statSync(dir).isDirectory()) continue;

        const broken = new Set();

        for (const file of walkFiles(dir)) {
            if (!file.endsWith('.vue')) continue;
            for (const rule of scanTemplate(fs.readFileSync(file, 'utf8'))) broken.add(rule);
        }

        if (broken.size) out[id] = [...broken].sort();
    }

    return out;
}

export function walkFiles(dir) {
    const out = [];
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) out.push(...walkFiles(full));
        else out.push(full);
    }
    return out;
}
