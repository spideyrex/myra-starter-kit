<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();

        // Categories
        $tech = Category::create([
            'name' => 'Technology',
            'slug' => 'technology',
            'description' => 'Articles about technology, software, and innovation.',
        ]);

        $news = Category::create([
            'name' => 'News',
            'slug' => 'news',
            'description' => 'Latest news and announcements.',
        ]);

        $tutorials = Category::create([
            'name' => 'Tutorials',
            'slug' => 'tutorials',
            'description' => 'Step-by-step guides and how-tos.',
        ]);

        // Pages
        Page::create([
            'title' => 'Terms of Service',
            'slug' => 'terms-of-service',
            'body_html' => '<h2>Terms of Service</h2><p>Welcome to our platform. By accessing or using our services, you agree to be bound by these Terms of Service.</p><h3>1. Acceptance of Terms</h3><p>By creating an account or using any part of our services, you acknowledge that you have read, understood, and agree to be bound by these terms.</p><h3>2. Use of Services</h3><p>You agree to use our services only for lawful purposes. You must not use our services in any way that could damage, disable, or impair our servers or networks.</p><h3>3. User Accounts</h3><p>You are responsible for maintaining the confidentiality of your account credentials. You agree to notify us immediately of any unauthorized use of your account.</p><h3>4. Intellectual Property</h3><p>All content, features, and functionality of our services are owned by us and are protected by international copyright, trademark, and other intellectual property laws.</p><h3>5. Termination</h3><p>We may terminate or suspend your account at any time, without prior notice, for conduct that we believe violates these Terms or is harmful to other users.</p><h3>6. Changes to Terms</h3><p>We reserve the right to modify these terms at any time. We will notify users of any material changes via email or through our platform.</p>',
            'excerpt' => 'Our terms of service outline the rules and guidelines for using our platform.',
            'meta' => ['meta_title' => 'Terms of Service', 'meta_description' => 'Read our terms of service to understand the rules for using our platform.', 'meta_keywords' => 'terms, service, legal'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'body_html' => '<h2>Privacy Policy</h2><p>Your privacy is important to us. This Privacy Policy explains how we collect, use, disclose, and safeguard your information.</p><h3>Information We Collect</h3><p>We collect information you provide directly, such as your name, email address, and any other information you choose to provide when creating an account or using our services.</p><h3>How We Use Your Information</h3><p>We use the information we collect to provide, maintain, and improve our services, communicate with you, and protect our users.</p><h3>Data Security</h3><p>We implement appropriate technical and organizational security measures to protect your personal data against unauthorized access, alteration, disclosure, or destruction.</p><h3>Your Rights</h3><p>You have the right to access, correct, or delete your personal information. You may also object to or restrict certain processing of your data.</p><h3>Contact Us</h3><p>If you have any questions about this Privacy Policy, please contact us through our support channels.</p>',
            'excerpt' => 'Learn how we collect, use, and protect your personal information.',
            'meta' => ['meta_title' => 'Privacy Policy', 'meta_description' => 'Our privacy policy explains how we handle your personal data.', 'meta_keywords' => 'privacy, policy, data, protection'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        Page::create([
            'title' => 'About Us',
            'slug' => 'about-us',
            'body_html' => '<h2>About Us</h2><p>We are a team of passionate developers and designers dedicated to building tools that help businesses grow and succeed online.</p><h3>Our Mission</h3><p>To empower organizations with intuitive, powerful, and secure software solutions that streamline their operations and drive growth.</p><h3>Our Story</h3><p>Founded in 2024, we started with a simple idea: make enterprise-grade software accessible to everyone. Today, we serve thousands of businesses worldwide.</p><h3>Our Values</h3><ul><li><strong>Innovation</strong> — We constantly push boundaries to deliver cutting-edge solutions.</li><li><strong>Transparency</strong> — We believe in open communication with our users and stakeholders.</li><li><strong>Quality</strong> — Every line of code we write is crafted with care and attention to detail.</li><li><strong>Community</strong> — We value our community and actively contribute to open-source projects.</li></ul>',
            'excerpt' => 'Learn about our mission, story, and the values that drive us.',
            'meta' => ['meta_title' => 'About Us', 'meta_description' => 'Learn about our team, mission, and values.', 'meta_keywords' => 'about, team, mission'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        Page::create([
            'title' => 'Internal Documentation',
            'slug' => 'internal-docs',
            'body_html' => '<h2>Internal Documentation</h2><p>This page contains internal documentation that is only accessible to authenticated users.</p><h3>API Endpoints</h3><p>Refer to the API documentation for details on available endpoints and authentication methods.</p><h3>Deployment Guide</h3><p>Follow the standard deployment process outlined in the DevOps handbook.</p>',
            'excerpt' => 'Internal documentation for team members.',
            'status' => 'published',
            'is_public' => false,
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        // Articles
        Article::create([
            'title' => 'Getting Started with Our Platform',
            'slug' => 'getting-started',
            'body_html' => '<h2>Getting Started</h2><p>Welcome to our platform! This guide will help you get up and running quickly.</p><h3>Step 1: Create Your Account</h3><p>Sign up using your email address. You\'ll receive a verification email to confirm your account.</p><h3>Step 2: Set Up Your Profile</h3><p>Navigate to the Profile section to add your name, avatar, and contact information.</p><h3>Step 3: Explore the Dashboard</h3><p>The dashboard provides an overview of your account with key metrics and quick actions. Take some time to familiarize yourself with the layout.</p><h3>Step 4: Configure Settings</h3><p>Visit the Settings section to customize your experience. You can update notification preferences, change your password, and enable two-factor authentication.</p><h3>Need Help?</h3><p>If you run into any issues, check our documentation or reach out to our support team.</p>',
            'excerpt' => 'A comprehensive guide to help you get started with our platform.',
            'meta' => ['meta_title' => 'Getting Started Guide', 'meta_description' => 'Learn how to get started with our platform in just a few steps.', 'meta_keywords' => 'getting started, guide, tutorial'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now()->subDays(5),
            'tags' => ['beginner', 'guide', 'onboarding'],
            'category_id' => $tutorials->id,
            'created_by' => $user->id,
        ]);

        Article::create([
            'title' => 'What\'s New in Version 2.0',
            'slug' => 'whats-new-v2',
            'body_html' => '<h2>What\'s New in Version 2.0</h2><p>We\'re excited to announce the release of version 2.0 with a host of new features and improvements.</p><h3>New Dashboard</h3><p>A completely redesigned dashboard with customizable widgets, real-time analytics, and improved performance.</p><h3>Enhanced Security</h3><p>Two-factor authentication, session management, and activity logging are now built into the core platform.</p><h3>Content Management</h3><p>A brand new CMS system with pages, articles, and categories. Create and manage content directly from the admin panel.</p><h3>Performance Improvements</h3><p>We\'ve optimized database queries and added caching throughout the application, resulting in up to 3x faster page loads.</p>',
            'excerpt' => 'Discover all the exciting new features and improvements in version 2.0.',
            'meta' => ['meta_title' => 'What\'s New in v2.0', 'meta_description' => 'Explore the new features in version 2.0 of our platform.', 'meta_keywords' => 'release, v2, features, update'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now()->subDays(2),
            'tags' => ['release', 'features', 'announcement'],
            'category_id' => $news->id,
            'created_by' => $user->id,
        ]);

        Article::create([
            'title' => 'Building RESTful APIs with Laravel',
            'slug' => 'building-restful-apis-laravel',
            'body_html' => '<h2>Building RESTful APIs with Laravel</h2><p>Laravel provides an elegant and powerful toolkit for building RESTful APIs. In this article, we\'ll walk through the essentials.</p><h3>Setting Up Routes</h3><p>Use <code>Route::apiResource()</code> to define resourceful API routes. This automatically generates routes for index, show, store, update, and destroy actions.</p><h3>Creating Controllers</h3><p>API controllers should extend the base Controller class and use API Resources to transform your data before sending it to clients.</p><h3>Validation</h3><p>Use Form Requests to validate incoming data. This keeps your controller clean and your validation logic organized.</p><h3>Authentication</h3><p>Laravel Sanctum provides a lightweight authentication system for SPAs and APIs. It supports both token-based and cookie-based authentication.</p><h3>Testing</h3><p>Always write tests for your API endpoints. Use Laravel\'s built-in testing tools to make HTTP requests and assert responses.</p>',
            'excerpt' => 'Learn how to build robust RESTful APIs using Laravel framework.',
            'meta' => ['meta_title' => 'Building RESTful APIs with Laravel', 'meta_description' => 'A guide to building RESTful APIs using Laravel.', 'meta_keywords' => 'laravel, api, rest, tutorial'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now()->subDays(3),
            'tags' => ['laravel', 'api', 'backend', 'php'],
            'category_id' => $tech->id,
            'created_by' => $user->id,
        ]);

        Article::create([
            'title' => 'Introduction to Vue 3 Composition API',
            'slug' => 'vue3-composition-api',
            'body_html' => '<h2>Introduction to Vue 3 Composition API</h2><p>The Composition API is one of the most significant features in Vue 3. It provides a more flexible and powerful way to organize component logic.</p><h3>Why Composition API?</h3><p>The Options API works well for simple components, but as components grow in complexity, related logic gets scattered across different options. The Composition API lets you group related logic together.</p><h3>Setup Function</h3><p>The <code>setup()</code> function is the entry point for the Composition API. You can also use the <code>&lt;script setup&gt;</code> syntax for a more concise approach.</p><h3>Reactivity</h3><p>Use <code>ref()</code> for primitive values and <code>reactive()</code> for objects. These create reactive state that automatically triggers re-renders when values change.</p><h3>Composables</h3><p>Extract and reuse stateful logic across components using composables — functions that encapsulate reactive state and related methods.</p>',
            'excerpt' => 'Understand the fundamentals of Vue 3 Composition API and how it improves code organization.',
            'meta' => ['meta_title' => 'Vue 3 Composition API Guide', 'meta_description' => 'Learn the Vue 3 Composition API with practical examples.', 'meta_keywords' => 'vue, vue3, composition api, frontend'],
            'status' => 'published',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'tags' => ['vue', 'frontend', 'javascript'],
            'category_id' => $tech->id,
            'created_by' => $user->id,
        ]);

        Article::create([
            'title' => 'Draft: Upcoming Features Roadmap',
            'slug' => 'upcoming-features-roadmap',
            'body_html' => '<h2>Upcoming Features Roadmap</h2><p>This is a draft article about upcoming features. Not yet published.</p>',
            'excerpt' => 'A preview of what\'s coming next to our platform.',
            'status' => 'draft',
            'is_public' => true,
            'tags' => ['roadmap', 'preview'],
            'category_id' => $news->id,
            'created_by' => $user->id,
        ]);
    }
}
