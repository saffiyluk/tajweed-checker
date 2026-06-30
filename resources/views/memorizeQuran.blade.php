{{-- resources/views/memorizeQuran.blade.php --}}
@extends('layouts.app')

@section('title', 'Quran Memorization - ' . config('app.name'))

@push('styles')
<style>
    :root {
        --mq-primary: #0f766e;
        --mq-primary-dark: #134e4a;
        --mq-primary-soft: #ccfbf1;
        --mq-gold: #d6a84f;
        --mq-gold-soft: #fff7df;
        --mq-bg: #f4f8f7;
        --mq-card: rgba(255, 255, 255, 0.92);
        --mq-card-solid: #ffffff;
        --mq-ink: #10201d;
        --mq-muted: #64748b;
        --mq-line: #d9e6e3;
        --mq-soft: #f8fbfb;
        --mq-right: #15803d;
        --mq-right-soft: #ecfdf5;
        --mq-wrong: #dc2626;
        --mq-wrong-soft: #fef2f2;
        --mq-warn: #d97706;
        --mq-warn-soft: #fffbeb;
        --mq-shadow: 0 22px 60px rgba(15, 23, 42, 0.10);
        --mq-shadow-soft: 0 12px 34px rgba(15, 23, 42, 0.07);
        --mq-radius: 26px;
    }

    .memorize-page {
        color: var(--mq-ink);
        min-height: calc(100vh - 80px);
        background:
            radial-gradient(circle at 10% 5%, rgba(15, 118, 110, 0.14), transparent 28%),
            radial-gradient(circle at 90% 15%, rgba(214, 168, 79, 0.16), transparent 26%),
            linear-gradient(180deg, #f8fffd 0%, var(--mq-bg) 42%, #f8fafc 100%);
    }

    .memorize-shell {
        max-width: 1240px;
        margin: 0 auto;
        padding: 2rem 1rem 4rem;
    }

    .memorize-hero {
        position: relative;
        overflow: hidden;
        border-radius: 32px;
        padding: clamp(1.2rem, 3vw, 2rem);
        margin-bottom: 1rem;
        background:
            linear-gradient(135deg, rgba(19, 78, 74, 0.96), rgba(15, 118, 110, 0.92)),
            radial-gradient(circle at top right, rgba(214, 168, 79, 0.30), transparent 34%);
        box-shadow: var(--mq-shadow);
        color: white;
    }

    .memorize-hero::before {
        content: "";
        position: absolute;
        inset: auto -80px -120px auto;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.10);
    }

    .memorize-hero::after {
        content: "حفظ";
        position: absolute;
        right: 2rem;
        top: 0.2rem;
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: clamp(4rem, 12vw, 9rem);
        line-height: 1;
        color: rgba(255, 255, 255, 0.06);
        pointer-events: none;
    }

    .memorize-topbar {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: end;
    }

    .memorize-title .kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-bottom: 0.75rem;
        padding: 0.4rem 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.24);
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.10);
        font-size: 0.76rem;
        font-weight: 900;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .memorize-title h1 {
        margin: 0;
        font-size: clamp(2rem, 4.8vw, 3.55rem);
        font-weight: 950;
        letter-spacing: -0.04em;
    }

    .memorize-title p {
        margin: 0.55rem 0 0;
        color: rgba(255, 255, 255, 0.80);
        line-height: 1.7;
        max-width: 760px;
        font-weight: 650;
    }

    .memorize-actions,
    .session-actions,
    .ayah-stepper,
    .recite-toolbar {
        display: flex;
        gap: 0.65rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .memorize-actions {
        justify-content: flex-end;
    }

    .memorize-btn,
    .icon-btn {
        border: 0;
        border-radius: 16px;
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-weight: 900;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, border-color 0.18s ease;
    }

    .memorize-btn {
        padding: 0.76rem 1rem;
        background: linear-gradient(135deg, var(--mq-primary), #14b8a6);
        color: white;
        box-shadow: 0 14px 30px rgba(15, 118, 110, 0.20);
    }

    .memorize-btn:hover,
    .memorize-btn:focus {
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(15, 118, 110, 0.24);
    }

    .memorize-btn.secondary {
        background: rgba(255, 255, 255, 0.14);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.24);
        box-shadow: none;
        backdrop-filter: blur(12px);
    }

    .memorize-controls .memorize-btn.secondary,
    .recite-body .memorize-btn.secondary,
    .progress-panel .memorize-btn.secondary {
        background: #ffffff;
        color: var(--mq-primary-dark);
        border: 1px solid var(--mq-line);
    }

    .memorize-btn.secondary:hover,
    .memorize-btn.secondary:focus {
        background: rgba(255, 255, 255, 0.22);
        color: white;
    }

    .memorize-controls .memorize-btn.secondary:hover,
    .recite-body .memorize-btn.secondary:hover,
    .progress-panel .memorize-btn.secondary:hover {
        background: var(--mq-primary-soft);
        color: var(--mq-primary-dark);
    }

    .icon-btn {
        width: 46px;
        height: 46px;
        background: white;
        color: var(--mq-primary-dark);
        border: 1px solid var(--mq-line);
        box-shadow: var(--mq-shadow-soft);
    }

    .icon-btn:hover,
    .icon-btn:focus {
        color: var(--mq-primary-dark);
        background: var(--mq-primary-soft);
        transform: translateY(-1px);
    }

    .icon-btn:disabled,
    .memorize-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .memorize-controls {
        background: var(--mq-card);
        border: 1px solid rgba(217, 230, 227, 0.78);
        border-radius: var(--mq-radius);
        box-shadow: var(--mq-shadow-soft);
        padding: 1rem;
        display: grid;
        grid-template-columns: minmax(0, 1.25fr) minmax(190px, 0.55fr) auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 1.25rem;
        backdrop-filter: blur(18px);
    }

    .control-group label {
        display: block;
        color: var(--mq-muted);
        font-size: 0.74rem;
        font-weight: 950;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.45rem;
    }

    .select-clean,
    .manual-input {
        width: 100%;
        min-height: 50px;
        border-radius: 16px;
        border: 1px solid var(--mq-line);
        background: var(--mq-soft);
        color: var(--mq-ink);
        padding: 0.75rem 1rem;
        font-weight: 800;
        outline: none;
        transition: 0.18s ease;
    }

    .select-clean:focus,
    .manual-input:focus {
        background: white;
        border-color: var(--mq-primary);
        box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.12);
    }

    .memorize-workspace {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 1.25rem;
        align-items: start;
    }

    .recite-panel,
    .progress-panel,
    .empty-state {
        background: var(--mq-card-solid);
        border: 1px solid var(--mq-line);
        border-radius: var(--mq-radius);
        box-shadow: var(--mq-shadow);
        overflow: hidden;
    }

    .surah-strip {
        padding: 1.2rem 1.25rem;
        background:
            radial-gradient(circle at 16% 24%, rgba(15, 118, 110, 0.12), transparent 28%),
            linear-gradient(135deg, #ffffff, #f0fdfa 58%, #fffaf0);
        border-bottom: 1px solid var(--mq-line);
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 1rem;
        align-items: center;
    }

    .surah-header-line {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        flex-wrap: wrap;
    }

    .surah-name-ar {
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: clamp(2.1rem, 5vw, 3.55rem);
        color: var(--mq-primary-dark);
        line-height: 1.1;
        direction: rtl;
    }

    .surah-name-en {
        font-weight: 950;
        font-size: 1.14rem;
        margin-top: 0.3rem;
    }

    .surah-meta {
        color: var(--mq-muted);
        font-size: 0.86rem;
        font-weight: 800;
        margin-top: 0.2rem;
    }

    .session-pill {
        background: white;
        border: 1px solid #99f6e4;
        border-radius: 999px;
        color: var(--mq-primary-dark);
        padding: 0.65rem 0.9rem;
        font-weight: 950;
        white-space: nowrap;
        box-shadow: 0 10px 24px rgba(15, 118, 110, 0.08);
    }

    .recite-body {
        padding: 1.25rem;
    }

    .ayah-stepper {
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .ayah-label {
        min-height: 46px;
        padding: 0.65rem 1rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: var(--mq-soft);
        border: 1px solid var(--mq-line);
        font-weight: 950;
        color: var(--mq-ink);
        text-align: center;
    }

    .live-tracker {
        border: 1px solid #99f6e4;
        background:
            linear-gradient(135deg, #f0fdfa, #ffffff),
            radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 34%);
        border-radius: 20px;
        padding: 1rem;
        margin-bottom: 1rem;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.85rem;
        align-items: center;
    }

    .tracker-kicker {
        color: var(--mq-primary-dark);
        font-size: 0.72rem;
        font-weight: 950;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    .tracker-title {
        color: var(--mq-ink);
        font-size: 1.02rem;
        font-weight: 950;
    }

    .tracker-subtitle {
        color: var(--mq-muted);
        font-size: 0.84rem;
        font-weight: 800;
        margin-top: 0.2rem;
    }

    .mad-hint {
        color: var(--mq-warn);
        font-weight: 950;
    }

    .tracker-state {
        min-height: 42px;
        border-radius: 999px;
        background: white;
        border: 1px solid var(--mq-line);
        color: var(--mq-muted);
        padding: 0.5rem 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.83rem;
        font-weight: 950;
        white-space: nowrap;
    }

    .tracker-state.live {
        color: var(--mq-primary-dark);
        border-color: #5eead4;
        background: #ecfeff;
    }

    .tracker-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #94a3b8;
    }

    .tracker-state.live .tracker-dot {
        background: var(--mq-primary);
        box-shadow: 0 0 0 6px rgba(15, 118, 110, 0.12);
        animation: mq-pulse 1.1s infinite;
    }

    @keyframes mq-pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(0.75); opacity: 0.68; }
    }

    .chunk-stage {
        min-height: 590px;
        border: 1px solid var(--mq-line);
        background:
            radial-gradient(circle at top, rgba(15, 118, 110, 0.05), transparent 30%),
            #eef5f3;
        border-radius: 24px;
        padding: clamp(0.7rem, 2vw, 1.15rem);
    }

    .chunk-list {
        width: min(100%, 780px);
        min-height: 550px;
        margin: 0 auto;
        direction: rtl;
        text-align: justify;
        display: flex;
        flex-wrap: wrap;
        gap: 0.42rem 0.55rem;
        align-content: flex-start;
        justify-content: flex-start;
        background:
            linear-gradient(90deg, rgba(15, 118, 110, 0.035) 1px, transparent 1px),
            linear-gradient(0deg, rgba(214, 168, 79, 0.12) 1px, transparent 1px),
            #fffdf6;
        background-size: 100% 58px;
        border: 5px double rgba(214, 168, 79, 0.78);
        border-radius: 22px;
        box-shadow:
            inset 0 0 0 13px rgba(15, 118, 110, 0.035),
            0 18px 38px rgba(15, 23, 42, 0.10);
        padding: clamp(1.25rem, 4vw, 2.35rem);
        position: relative;
    }

    .chunk-list::before,
    .chunk-list::after {
        content: "";
        position: absolute;
        left: 1rem;
        right: 1rem;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(214, 168, 79, 0.65), transparent);
    }

    .chunk-list::before { top: 1rem; }
    .chunk-list::after { bottom: 1rem; }

    .chunk {
        min-height: 58px;
        min-width: var(--slot-width, 74px);
        max-width: 100%;
        border-radius: 12px;
        border: 0;
        border-bottom: 2px solid #cbd5e1;
        background: transparent;
        color: transparent;
        padding: 0.18rem 0.28rem 0.1rem;
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: clamp(2rem, 4.3vw, 2.72rem);
        line-height: 1.75;
        transition: 0.2s ease;
        position: relative;
        overflow-wrap: anywhere;
    }

    .chunk::before {
        content: "";
        position: absolute;
        left: 0.25rem;
        right: 0.25rem;
        bottom: 0.67rem;
        height: 8px;
        border-radius: 999px;
        background: #dde6ef;
    }

    .chunk.active {
        background: rgba(15, 118, 110, 0.10);
        border-bottom-color: var(--mq-primary);
        box-shadow: inset 0 -3px 0 rgba(15, 118, 110, 0.18);
    }

    .chunk.correct {
        color: var(--mq-ink);
        border-bottom-color: transparent;
        background: transparent;
    }

    .chunk.wrong {
        color: var(--mq-wrong);
        background: var(--mq-wrong-soft);
        border-bottom-color: var(--mq-wrong);
        animation: shake 0.24s ease;
    }

    .chunk.missing {
        color: var(--mq-warn);
        background: var(--mq-warn-soft);
        border-bottom-color: var(--mq-warn);
    }

    .chunk.correct::before,
    .chunk.wrong::before,
    .chunk.missing::before,
    .chunk.revealed::before {
        content: none;
    }

    .chunk.revealed {
        color: var(--mq-ink);
        border-bottom-color: var(--mq-gold);
    }

    .chunk.has-mad {
        border-bottom-color: var(--mq-gold);
    }

    .chunk.has-mad::after {
        content: attr(data-mad);
        position: absolute;
        top: -0.55rem;
        right: 0.12rem;
        color: var(--mq-warn);
        font-family: 'Poppins', sans-serif;
        font-size: 0.58rem;
        font-weight: 950;
        direction: ltr;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        35% { transform: translateX(-4px); }
        70% { transform: translateX(4px); }
    }

    .recite-toolbar {
        justify-content: space-between;
        margin-top: 1rem;
        padding: 0.85rem;
        border-radius: 22px;
        background: var(--mq-soft);
        border: 1px solid var(--mq-line);
    }

    .mic-button {
        width: 74px;
        height: 74px;
        border-radius: 50%;
        border: 0;
        background: linear-gradient(135deg, var(--mq-primary), #14b8a6);
        color: white;
        display: grid;
        place-items: center;
        font-size: 1.55rem;
        box-shadow: 0 18px 34px rgba(15, 118, 110, 0.24);
        cursor: pointer;
        transition: 0.2s ease;
        flex: 0 0 auto;
    }

    .mic-button:hover {
        transform: translateY(-1px) scale(1.02);
    }

    .mic-button.listening {
        background: var(--mq-wrong);
        box-shadow: 0 0 0 11px rgba(220, 38, 38, 0.10), 0 18px 34px rgba(220, 38, 38, 0.20);
    }

    .mic-button:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    .status-line {
        color: var(--mq-muted);
        font-weight: 850;
        line-height: 1.55;
    }

    .transcript-box {
        margin-top: 1rem;
        border-radius: 20px;
        background: #f8fafc;
        border: 1px solid var(--mq-line);
        padding: 1rem;
        min-height: 76px;
        color: var(--mq-muted);
        direction: rtl;
        text-align: right;
        font-family: "Amiri", "Scheherazade New", serif;
        font-size: 1.48rem;
        line-height: 1.8;
    }

    .manual-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.75rem;
        margin-top: 1rem;
    }

    .progress-panel {
        position: sticky;
        top: 1rem;
        padding: 1rem;
    }

    .progress-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        font-weight: 950;
        margin-bottom: 0.8rem;
    }

    .progress-title span {
        color: var(--mq-muted);
        font-size: 0.78rem;
        font-weight: 850;
    }

    .meter {
        height: 12px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        margin-bottom: 0.9rem;
    }

    .meter-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, var(--mq-primary), #22c55e);
        transition: width 0.25s ease;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .stat-tile {
        background: var(--mq-soft);
        border: 1px solid var(--mq-line);
        border-radius: 18px;
        padding: 0.88rem;
    }

    .stat-value {
        font-size: 1.55rem;
        font-weight: 950;
        color: var(--mq-primary-dark);
        line-height: 1.1;
    }

    .stat-label {
        color: var(--mq-muted);
        font-size: 0.78rem;
        font-weight: 850;
        margin-top: 0.18rem;
    }

    .hint-card {
        margin-top: 0.9rem;
        border-radius: 20px;
        background: linear-gradient(135deg, #fff7df, #ffffff);
        border: 1px solid rgba(214, 168, 79, 0.32);
        padding: 0.9rem;
        color: #7c520f;
        font-size: 0.86rem;
        line-height: 1.55;
        font-weight: 750;
    }

    .hint-card strong {
        display: block;
        color: #92400e;
        font-weight: 950;
        margin-bottom: 0.22rem;
    }

    .ayah-map {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(44px, 1fr));
        gap: 0.5rem;
        margin-top: 1rem;
        max-height: 320px;
        overflow-y: auto;
        padding-right: 0.2rem;
    }

    .ayah-dot {
        min-height: 42px;
        border: 1px solid var(--mq-line);
        background: white;
        border-radius: 14px;
        font-weight: 950;
        color: var(--mq-muted);
        cursor: pointer;
        transition: 0.18s ease;
    }

    .ayah-dot:hover {
        border-color: var(--mq-primary);
        color: var(--mq-primary-dark);
    }

    .ayah-dot.active {
        background: #f0fdfa;
        border-color: var(--mq-primary);
        color: var(--mq-primary-dark);
    }

    .ayah-dot.done {
        background: var(--mq-right-soft);
        border-color: #bbf7d0;
        color: var(--mq-right);
    }

    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        color: var(--mq-muted);
    }

    .empty-state h3 {
        color: var(--mq-ink);
        font-weight: 950;
    }

    @media (max-width: 1050px) {
        .memorize-workspace {
            grid-template-columns: 1fr;
        }

        .progress-panel {
            position: static;
        }
    }

    @media (max-width: 880px) {
        .memorize-topbar,
        .memorize-controls {
            grid-template-columns: 1fr;
        }

        .memorize-actions,
        .session-actions {
            justify-content: flex-start;
        }

        .surah-strip,
        .live-tracker {
            grid-template-columns: 1fr;
        }

        .tracker-state {
            justify-self: flex-start;
        }
    }

    @media (max-width: 576px) {
        .memorize-shell {
            padding: 1rem 0.85rem 3rem;
        }

        .memorize-hero {
            border-radius: 24px;
        }

        .recite-body,
        .surah-strip {
            padding: 1rem;
        }

        .chunk-stage {
            min-height: 430px;
            padding: 0.65rem;
        }

        .chunk-list {
            min-height: 405px;
            border-width: 4px;
            padding: 1rem;
        }

        .chunk {
            min-height: 48px;
            min-width: var(--slot-width, 58px);
            font-size: 1.85rem;
        }

        .manual-row,
        .stats-grid,
        .recite-toolbar {
            grid-template-columns: 1fr;
        }

        .recite-toolbar {
            align-items: flex-start;
        }
    }

    /* English redesign: cleaner presentation layout */
    :root {
        --mq-primary: #0b7f73;
        --mq-primary-dark: #083b3f;
        --mq-primary-soft: #d9fbf4;
        --mq-gold: #c99a2e;
        --mq-gold-soft: #fff7df;
        --mq-bg: #eef6f4;
        --mq-card: rgba(255, 255, 255, 0.95);
        --mq-ink: #0b1720;
        --mq-muted: #5f6f7b;
        --mq-line: rgba(12, 72, 75, 0.13);
        --mq-soft: #f6faf9;
        --mq-shadow: 0 24px 70px rgba(7, 47, 51, 0.12);
        --mq-shadow-soft: 0 14px 35px rgba(7, 47, 51, 0.08);
    }

    .memorize-page {
        background:
            radial-gradient(circle at 8% 10%, rgba(11, 127, 115, 0.18), transparent 30%),
            radial-gradient(circle at 92% 8%, rgba(201, 154, 46, 0.16), transparent 28%),
            linear-gradient(180deg, #fbfffe 0%, #eef6f4 48%, #f8fafc 100%);
    }

    .memorize-hero {
        border-radius: 34px;
        padding: clamp(1.4rem, 3vw, 2.2rem);
        background:
            linear-gradient(135deg, rgba(8, 59, 63, 0.98) 0%, rgba(11, 127, 115, 0.96) 58%, rgba(19, 121, 103, 0.94) 100%);
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .memorize-hero::after {
        content: "القرآن";
        right: 1.35rem;
        top: 0.35rem;
        font-size: clamp(4.5rem, 14vw, 10rem);
        opacity: 0.08;
    }

    .memorize-title h1 {
        max-width: 720px;
        line-height: 0.98;
    }

    .memorize-title .kicker {
        background: rgba(255, 255, 255, 0.13);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
    }

    .memorize-controls {
        margin-top: -0.35rem;
        position: relative;
        z-index: 2;
        border-radius: 24px;
        box-shadow: 0 18px 50px rgba(7, 47, 51, 0.10);
    }

    .recite-panel,
    .progress-panel {
        border-radius: 28px;
        border-color: rgba(12, 72, 75, 0.12);
    }

    .surah-strip {
        background:
            linear-gradient(135deg, rgba(255,255,255,0.98), rgba(249, 255, 253, 0.98)),
            radial-gradient(circle at top right, rgba(201, 154, 46, 0.12), transparent 42%);
        border-bottom: 1px solid rgba(12, 72, 75, 0.10);
    }

    .surah-name-ar {
        color: var(--mq-primary-dark);
    }

    .session-pill,
    .tracker-state {
        background: #ecfdf5;
        color: var(--mq-primary-dark);
        border: 1px solid rgba(11, 127, 115, 0.14);
    }

    .live-tracker {
        background: linear-gradient(135deg, #f7fffd, #ffffff);
        border-color: rgba(11, 127, 115, 0.14);
    }

    .chunk-stage {
        background:
            linear-gradient(180deg, rgba(248, 250, 252, 0.65), rgba(240, 253, 250, 0.80));
        border-radius: 24px;
    }

    .chunk-list {
        border-radius: 24px;
        border-color: rgba(11, 127, 115, 0.10);
        background:
            linear-gradient(180deg, rgba(255,255,255,0.96), rgba(255,255,255,0.90)),
            repeating-linear-gradient(0deg, transparent 0 63px, rgba(11, 127, 115, 0.06) 64px 65px);
    }

    .chunk {
        border-radius: 17px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .mic-button {
        box-shadow: 0 18px 34px rgba(11, 127, 115, 0.26);
    }

    .progress-panel {
        position: sticky;
        top: 1rem;
        background:
            linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248, 253, 251, 0.98));
    }

    .progress-title {
        color: var(--mq-primary-dark);
    }

    .hint-card {
        border-left: 4px solid var(--mq-gold);
    }

</style>
@endpush

@section('content')
<div class="memorize-page" id="memorizePage">
    <div class="memorize-shell">
        <section class="memorize-hero">
            <div class="memorize-topbar">
                <div class="memorize-title">
                    <div class="kicker"><i class="fas fa-microphone-lines"></i> Live Memorization Practice</div>
                    <h1>Quran Memorization</h1>
                    <p>Practice memorization with a blank ayah view. Recite clearly and the system will reveal each correct word step by step.</p>
                </div>

                <div class="memorize-actions">
                    <a href="{{ route('recite.quran', ['surah' => $currentSurah]) }}" class="memorize-btn secondary">
                        <i class="fas fa-book-open"></i> Reader
                    </a>
                    <a href="{{ route('tajweed.history') }}" class="memorize-btn secondary">
                        <i class="fas fa-clock-rotate-left"></i> History
                    </a>
                </div>
            </div>
        </section>

        <div class="memorize-controls">
            <div class="control-group">
                <label for="surahSelect">Select Surah</label>
                <select class="select-clean" id="surahSelect">
                    <option value="">Select a Surah</option>
                    @foreach($allSurahs as $surahOption)
                        <option value="{{ $surahOption['number'] }}" @selected(isset($currentSurah) && $currentSurah == $surahOption['number'])>
                            {{ $surahOption['number'] }}. {{ $surahOption['englishName'] }} ({{ $surahOption['name'] }})
                        </option>
                    @endforeach
                </select>
            </div>

            @if(isset($surah) && $surah)
                <div class="control-group">
                    <label for="ayahSelect">Start Ayah</label>
                    <select class="select-clean" id="ayahSelect">
                        @for($ayahNumber = 1; $ayahNumber <= $totalAyahs; $ayahNumber++)
                            <option value="{{ $ayahNumber }}" @selected($selectedAyah == $ayahNumber)>
                                Ayah {{ $ayahNumber }}
                            </option>
                        @endfor
                    </select>
                </div>
            @endif

            <div class="session-actions">
                <button type="button" class="memorize-btn secondary" id="revealBtn">
                    <i class="fas fa-eye"></i> Reveal
                </button>
                <button type="button" class="memorize-btn secondary" id="resetBtn">
                    <i class="fas fa-rotate-left"></i> Reset
                </button>
            </div>
        </div>

        @if(isset($surah) && $surah && count($ayahs) > 0)
            <div class="memorize-workspace">
                <section class="recite-panel">
                    <div class="surah-strip">
                        <div>
                            <div class="surah-name-ar">{{ $surah['name'] ?? '' }}</div>
                            <div class="surah-name-en">{{ $surah['englishName'] ?? 'Surah' }}</div>
                            <div class="surah-meta">
                                {{ $surah['englishNameTranslation'] ?? '' }} · {{ $surah['numberOfAyahs'] ?? $totalAyahs }} Ayahs
                            </div>
                        </div>

                        <div class="session-pill" id="sessionPill">
                            Ayah {{ $selectedAyah }} / {{ $totalAyahs }}
                        </div>
                    </div>

                    <div class="recite-body">
                        <div class="ayah-stepper">
                            <button type="button" class="icon-btn" id="prevAyahBtn" aria-label="Previous ayah">
                                <i class="fas fa-arrow-left"></i>
                            </button>

                            <div class="ayah-label" id="ayahLabel">
                                {{ $surah['englishName'] ?? 'Surah' }} {{ $currentSurah }}:{{ $selectedAyah }}
                            </div>

                            <button type="button" class="icon-btn" id="nextAyahBtn" aria-label="Next ayah">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>

                        <div class="live-tracker" id="liveTracker">
                            <div>
                                <div class="tracker-kicker">Now checking</div>
                                <div class="tracker-title" id="trackerTitle">
                                    {{ $surah['englishName'] ?? 'Surah' }} {{ $currentSurah }}:{{ $selectedAyah }}
                                </div>
                                <div class="tracker-subtitle" id="trackerSubtitle">
                                    Word 1 of 1
                                </div>
                            </div>

                            <div class="tracker-state" id="trackerState">
                                <span class="tracker-dot"></span>
                                <span id="trackerStateText">Ready</span>
                            </div>
                        </div>

                        <div class="chunk-stage">
                            <div class="chunk-list" id="chunkList" aria-label="Blank Quran memorization page"></div>
                        </div>

                        <div class="recite-toolbar">
                            <button type="button" class="mic-button" id="micBtn" aria-label="Start recording">
                                <i class="fas fa-microphone"></i>
                            </button>

                            <div class="status-line" id="statusLine">Ready</div>
                        </div>

                        <div class="transcript-box" id="transcriptBox" dir="rtl">...</div>

                        <div class="manual-row">
                            <input type="text" class="manual-input" id="manualInput" dir="rtl" placeholder="Type Arabic transcript here">
                            <button type="button" class="memorize-btn" id="manualBtn">
                                <i class="fas fa-check"></i> Check
                            </button>
                        </div>
                    </div>
                </section>

                <aside class="progress-panel">
                    <div class="progress-title">
                        Progress
                        <span>Current ayah</span>
                    </div>
                    <div class="meter">
                        <div class="meter-fill" id="meterFill"></div>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-tile">
                            <div class="stat-value" id="correctCount">0</div>
                            <div class="stat-label">Correct words</div>
                        </div>
                        <div class="stat-tile">
                            <div class="stat-value" id="wrongCount">0</div>
                            <div class="stat-label">Retry words</div>
                        </div>
                    </div>

                    <div class="hint-card">
                        <strong>Practice tip</strong>
                        Press the microphone, recite clearly, and the system will reveal the correct words as you go. Use Reveal to check the full ayah when needed.
                    </div>

                    <div class="ayah-map" id="ayahMap"></div>
                </aside>
            </div>
        @else
            <div class="empty-state">
                <i class="fas fa-circle-info fa-3x mb-3"></i>
                <h3>No Ayahs Loaded</h3>
                <p>Please try another surah.</p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ayahs = @json($ayahs ?? []);
    const currentSurah = @json($currentSurah ?? 1);
    const surahName = @json($surah['englishName'] ?? 'Surah');
    let currentAyahIndex = Math.max(0, (@json($selectedAyah ?? 1) - 1));
    let currentChunkIndex = 0;
    let correctChunks = 0;
    let wrongChunks = 0;
    let isListening = false;
    let mediaRecorder = null;
    let mediaStream = null;
    let audioChunks = [];
    let wordStates = [];
    let lastAlignment = [];
    let lastExtraWords = [];
    let speechRecognition = null;
    let liveTranscriptBuffer = '';
    let lastProcessedWordCount = 0;
    let pendingWrongWord = null;
    let pendingWrongCount = 0;
    let pendingSpokenWords = [];
    let liveSpokenWords = [];
    let lastInterimSignature = '';
    let completionMoveTimer = null;
    const completedAyahs = new Set();
    const wrongConfirmThreshold = 2;
    const autoMoveNextAyah = true;
    const autoMoveDelayMs = 1000;
    const transcribeUrl = @json(route('memorize.transcribe'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const surahSelect = document.getElementById('surahSelect');
    const ayahSelect = document.getElementById('ayahSelect');
    const revealBtn = document.getElementById('revealBtn');
    const resetBtn = document.getElementById('resetBtn');
    const prevAyahBtn = document.getElementById('prevAyahBtn');
    const nextAyahBtn = document.getElementById('nextAyahBtn');
    const micBtn = document.getElementById('micBtn');
    const manualInput = document.getElementById('manualInput');
    const manualBtn = document.getElementById('manualBtn');
    const chunkList = document.getElementById('chunkList');
    const statusLine = document.getElementById('statusLine');
    const transcriptBox = document.getElementById('transcriptBox');
    const ayahLabel = document.getElementById('ayahLabel');
    const sessionPill = document.getElementById('sessionPill');
    const trackerTitle = document.getElementById('trackerTitle');
    const trackerSubtitle = document.getElementById('trackerSubtitle');
    const trackerState = document.getElementById('trackerState');
    const trackerStateText = document.getElementById('trackerStateText');
    const meterFill = document.getElementById('meterFill');
    const correctCount = document.getElementById('correctCount');
    const wrongCount = document.getElementById('wrongCount');
    const ayahMap = document.getElementById('ayahMap');

    function keepArabicClient(text) {
        const matches = (text || '').toString().match(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\s]+/g) || [];
        return matches.join(' ').replace(/\s+/g, ' ').trim();
    }

    function normalizeArabic(text) {
        return (text || '')
            .toString()
            .normalize('NFKD')
            .replace(/[\u0623\u0625\u0622\u0671]/g, '\u0627')
            .replace(/\u0624/g, '\u0648')
            .replace(/\u0626/g, '\u064A')
            .replace(/\u0629/g, '\u0647')
            .replace(/\u0649/g, '\u064A')
            .replace(/\u0670/g, '\u0627')
            .replace(/[\u0610-\u061A\u0640\u064B-\u065F\u06D6-\u06ED\u08D3-\u08FF]/g, '')
            .replace(/\p{Mark}/gu, '')
            .replace(/[^\u0621-\u064A\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getChunks(ayahText) {
        return (ayahText || '')
            .split(/\s+/)
            .filter(word => normalizeArabic(word) !== '');
    }

    function getCurrentAyah() {
        return ayahs[currentAyahIndex] || null;
    }

    function getCurrentChunks() {
        const ayah = getCurrentAyah();
        return ayah ? getChunks(ayah.text) : [];
    }

    function detectMadHint(word) {
        const text = word || '';
        const hasMaddah = text.includes('\u0653') || text.includes('~');
        const hasHamzah = /[\u0621\u0623\u0625\u0624\u0626]/.test(text);
        const hasShaddahOrSukun = /[\u0651\u0652]/.test(text);
        const hasDaggerAlif = text.includes('\u0670');
        const hasKasrahYa = /\u0650[\u064A\u06D2]/.test(text);
        const hasDammahWaw = /\u064F\u0648/.test(text);
        const hasFathahAlif = /\u064E[\u0627\u0670]/.test(text);
        const normalized = normalizeArabic(text);

        if (hasMaddah && hasShaddahOrSukun) {
            return { label: '6H', title: 'Mad Lazim / Mad Arid Lissukun', duration: '6 counts' };
        }

        if (hasMaddah && hasHamzah) {
            return { label: '4-5H', title: 'Mad Jaiz Munfasil / Mad Wajib Muttasil', duration: '4-5 counts' };
        }

        if (hasMaddah && normalized.length <= 3) {
            return { label: '6H', title: 'Mad Lazim Harfi', duration: '6 counts' };
        }

        if (hasDaggerAlif || hasKasrahYa || hasDammahWaw || hasFathahAlif) {
            return { label: '2H', title: 'Mad Asli / Mad Thabi\u2019i', duration: '2 counts' };
        }

        return null;
    }

    function updateStats() {
        const chunks = getCurrentChunks();
        const percent = chunks.length ? Math.round((correctChunks / chunks.length) * 100) : 0;

        if (meterFill) meterFill.style.width = `${percent}%`;
        if (correctCount) correctCount.textContent = correctChunks;
        if (wrongCount) wrongCount.textContent = wrongChunks;
    }

    function renderAyahMap() {
        if (!ayahMap) return;

        ayahMap.innerHTML = ayahs.map((ayah, index) => {
            const active = index === currentAyahIndex ? 'active' : '';
            const done = completedAyahs.has(index) ? 'done' : '';
            return `<button type="button" class="ayah-dot ${active} ${done}" data-index="${index}">${ayah.numberInSurah}</button>`;
        }).join('');

        ayahMap.querySelectorAll('.ayah-dot').forEach(button => {
            button.addEventListener('click', function () {
                loadAyah(Number(this.dataset.index));
            });
        });
    }

    function renderChunks() {
        if (!chunkList) return;

        const chunks = getCurrentChunks();
        chunkList.innerHTML = chunks.map((chunk, index) => {
            const state = wordStates[index] || '';
            const width = Math.max(54, Math.min(150, normalizeArabic(chunk).length * 22));
            const madHint = detectMadHint(chunk);
            const madClass = madHint ? 'has-mad' : '';
            const madLabel = madHint ? escapeHtml(madHint.label) : '';

            return `<span class="chunk ${state} ${madClass}" style="--slot-width: ${width}px" data-index="${index}" data-mad="${madLabel}">${escapeHtml(chunk)}</span>`;
        }).join('');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function setStatus(text) {
        if (statusLine) statusLine.textContent = text;
        if (trackerStateText) trackerStateText.textContent = text;

        if (trackerState) {
            trackerState.classList.toggle('live', isListening);
        }
    }

    function updateTracker() {
        const ayah = getCurrentAyah();
        const chunks = getCurrentChunks();
        const currentWord = Math.min(currentChunkIndex + 1, Math.max(1, chunks.length));

        if (trackerTitle && ayah) {
            trackerTitle.textContent = `${surahName} ${currentSurah}:${ayah.numberInSurah}`;
        }

        if (trackerSubtitle) {
            if (!chunks.length) {
                trackerSubtitle.textContent = 'No words loaded';
            } else {
                const madHint = detectMadHint(chunks[currentChunkIndex] || '');
                trackerSubtitle.innerHTML = madHint
                    ? `Word ${currentWord} of ${chunks.length} <span class="mad-hint">· ${madHint.title}: ${madHint.duration}</span>`
                    : `Word ${currentWord} of ${chunks.length}`;
            }
        }

        if (trackerState) {
            trackerState.classList.toggle('live', isListening);
        }
    }

    function loadAyah(index) {
        currentAyahIndex = Math.max(0, Math.min(ayahs.length - 1, index));
        currentChunkIndex = 0;
        correctChunks = 0;
        wrongChunks = 0;
        lastAlignment = [];
        lastExtraWords = [];
        liveTranscriptBuffer = '';
        lastProcessedWordCount = 0;
        pendingWrongWord = null;
        pendingWrongCount = 0;
        pendingSpokenWords = [];
        liveSpokenWords = [];
        lastInterimSignature = '';
        const ayah = getCurrentAyah();
        wordStates = getCurrentChunks().map(() => '');

        if (ayahLabel && ayah) ayahLabel.textContent = `${surahName} ${currentSurah}:${ayah.numberInSurah}`;
        if (sessionPill && ayah) sessionPill.textContent = `Ayah ${ayah.numberInSurah} / ${ayahs.length}`;
        if (ayahSelect && ayah) ayahSelect.value = ayah.numberInSurah;
        if (transcriptBox) transcriptBox.textContent = '...';
        if (manualInput) manualInput.value = '';

        renderChunks();
        renderAyahMap();
        updateButtons();
        updateStats();
        updateTracker();
        setStatus('Ready');
    }

    function updateButtons() {
        if (prevAyahBtn) prevAyahBtn.disabled = currentAyahIndex <= 0;
        if (nextAyahBtn) nextAyahBtn.disabled = currentAyahIndex >= ayahs.length - 1;
    }

    function activateChunk(index) {
        wordStates = wordStates.map(state => state === 'active' ? '' : state);

        if (index >= 0 && index < wordStates.length && !['correct', 'wrong', 'missing'].includes(wordStates[index])) {
            wordStates[index] = 'active';
        }

        renderChunks();
    }

    function revealCurrentChunk() {
        getCurrentChunks().forEach((word, index) => {
            if (!wordStates[index] || wordStates[index] === 'active') {
                wordStates[index] = 'revealed';
            }
        });

        renderChunks();
        setStatus('Word revealed');
    }

    function resetCurrentAyah() {
        currentChunkIndex = 0;
        correctChunks = 0;
        wrongChunks = 0;
        lastAlignment = [];
        lastExtraWords = [];
        liveTranscriptBuffer = '';
        lastProcessedWordCount = 0;
        pendingWrongWord = null;
        pendingWrongCount = 0;
        pendingSpokenWords = [];
        liveSpokenWords = [];
        lastInterimSignature = '';
        wordStates = getCurrentChunks().map(() => '');
        renderChunks();
        updateStats();
        updateTracker();
        setStatus('Ready');

        if (transcriptBox) transcriptBox.textContent = '...';
    }

    function wordsSimilar(expected, spoken) {
        if (!expected || !spoken) return false;
        if (expected === spoken) return true;

        const distance = levenshteinDistance(expected, spoken);
        const maxLength = Math.max(expected.length, spoken.length);

        return maxLength > 0 && (1 - (distance / maxLength)) >= 0.78;
    }

    function levenshteinDistance(a, b) {
        const rows = a.length + 1;
        const cols = b.length + 1;
        const dp = Array.from({ length: rows }, () => Array(cols).fill(0));

        for (let i = 0; i < rows; i++) dp[i][0] = i;
        for (let j = 0; j < cols; j++) dp[0][j] = j;

        for (let i = 1; i < rows; i++) {
            for (let j = 1; j < cols; j++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                dp[i][j] = Math.min(
                    dp[i - 1][j] + 1,
                    dp[i][j - 1] + 1,
                    dp[i - 1][j - 1] + cost
                );
            }
        }

        return dp[a.length][b.length];
    }

    function alignWords(expectedWords, spokenWords) {
        const m = expectedWords.length;
        const n = spokenWords.length;
        const dp = Array.from({ length: m + 1 }, () => Array(n + 1).fill(0));
        const back = Array.from({ length: m + 1 }, () => Array(n + 1).fill(null));

        for (let i = 1; i <= m; i++) {
            dp[i][0] = i;
            back[i][0] = 'missing';
        }

        for (let j = 1; j <= n; j++) {
            dp[0][j] = j;
            back[0][j] = 'extra';
        }

        for (let i = 1; i <= m; i++) {
            for (let j = 1; j <= n; j++) {
                const isMatch = wordsSimilar(expectedWords[i - 1], spokenWords[j - 1]);
                const replaceCost = isMatch ? 0 : 1;
                const candidates = [
                    { cost: dp[i - 1][j - 1] + replaceCost, action: isMatch ? 'correct' : 'wrong' },
                    { cost: dp[i - 1][j] + 1, action: 'missing' },
                    { cost: dp[i][j - 1] + 1, action: 'extra' },
                ];

                candidates.sort((a, b) => a.cost - b.cost);
                dp[i][j] = candidates[0].cost;
                back[i][j] = candidates[0].action;
            }
        }

        const alignment = [];
        let i = m;
        let j = n;

        while (i > 0 || j > 0) {
            const action = back[i][j];

            if ((action === 'correct' || action === 'wrong') && i > 0 && j > 0) {
                alignment.push({ expectedIndex: i - 1, expected: expectedWords[i - 1], spoken: spokenWords[j - 1], status: action });
                i--;
                j--;
            } else if (action === 'missing' && i > 0) {
                alignment.push({ expectedIndex: i - 1, expected: expectedWords[i - 1], spoken: null, status: 'missing' });
                i--;
            } else if (j > 0) {
                alignment.push({ expectedIndex: null, expected: null, spoken: spokenWords[j - 1], status: 'extra' });
                j--;
            } else {
                break;
            }
        }

        return alignment.reverse();
    }

    function renderFullAyahResult(alignment, spokenWords) {
        const expectedWords = getCurrentChunks();
        const states = expectedWords.map(() => '');

        correctChunks = 0;
        let wrongCount = 0;
        let missingCount = 0;
        let extraCount = 0;
        lastExtraWords = [];

        alignment.forEach(item => {
            if (item.status === 'extra') {
                extraCount++;
                lastExtraWords.push(item.spoken);
                return;
            }

            if (item.expectedIndex === null) return;

            states[item.expectedIndex] = item.status;

            if (item.status === 'correct') correctChunks++;
            else if (item.status === 'missing') missingCount++;
            else wrongCount++;
        });

        wordStates = states;
        wrongChunks = wrongCount + missingCount + extraCount;
        currentChunkIndex = Math.min(correctChunks, expectedWords.length);

        renderChunks();
        updateStats();
        updateTracker();

        const accuracy = expectedWords.length ? Math.round((correctChunks / expectedWords.length) * 100) : 0;

        if (accuracy >= 85) {
            completedAyahs.add(currentAyahIndex);
            renderAyahMap();
            setStatus('Ayah complete');
        } else {
            setStatus('Review highlighted words');
        }

        if (transcriptBox) {
            const extraText = lastExtraWords.length ? `\nExtra words detected: ${lastExtraWords.join(' ')}` : '';
            transcriptBox.textContent = spokenWords.length ? `${spokenWords.join(' ')}${extraText}` : 'No clear Arabic detected';
        }
    }

    function checkFullAyahTranscript(transcript) {
        const cleanedTranscript = keepArabicClient(transcript);
        const expectedWordsOriginal = getCurrentChunks();
        const expectedWordsNormalized = expectedWordsOriginal.map(normalizeArabic);
        const spokenWords = normalizeArabic(cleanedTranscript).split(' ').filter(Boolean);

        lastAlignment = alignWords(expectedWordsNormalized, spokenWords);
        renderFullAyahResult(lastAlignment, spokenWords);
    }

    function similarityRatio(a, b) {
        const maxLength = Math.max(a.length, b.length);
        if (maxLength === 0) return 0;
        return 1 - (levenshteinDistance(a, b) / maxLength);
    }

    const arabicLetterNames = {
        'الف': 'ا',
        'ألف': 'ا',
        'لام': 'ل',
        'ميم': 'م',
        'صاد': 'ص',
        'كاف': 'ك',
        'ها': 'ه',
        'هاء': 'ه',
        'يا': 'ي',
        'ياء': 'ي',
        'عين': 'ع',
        'سين': 'س',
        'قاف': 'ق',
        'نون': 'ن',
        'را': 'ر',
        'راء': 'ر',
        'طا': 'ط',
        'طاء': 'ط',
        'حا': 'ح',
        'حاء': 'ح',
    };

    function normalizeSpokenLetterPhrase(words) {
        const letters = [];

        for (const word of words) {
            const normalized = normalizeArabic(word);
            const letter = arabicLetterNames[normalized];

            if (!letter) return null;
            letters.push(letter);
        }

        return letters.join('');
    }

    function matchExpectedWord(spokenWord, expectedWord) {
        const spoken = normalizeArabic(spokenWord);
        const expected = normalizeArabic(expectedWord);
        const spokenWords = spoken.split(' ').filter(Boolean);
        const letterPhrase = spokenWords.length > 1 ? normalizeSpokenLetterPhrase(spokenWords) : null;

        if (!spoken || !expected) return false;
        if (letterPhrase && letterPhrase === expected) return true;
        if (spoken === expected) return true;
        if (spoken.includes(expected)) return true;

        if (expected.length >= 3 && spoken.length >= 3 && expected.includes(spoken)) return true;

        return similarityRatio(spoken, expected) >= 0.75;
    }

    function isPartialExpectedWord(spokenWord, expectedWord) {
        const spoken = normalizeArabic(spokenWord);
        const expected = normalizeArabic(expectedWord);

        if (!spoken || !expected || spoken.length < 2) return false;
        return expected.startsWith(spoken) && spoken.length < expected.length;
    }

    function extractNewArabicWords(transcript, options = {}) {
        const cleaned = keepArabicClient(transcript);
        const words = normalizeArabic(cleaned).split(' ').filter(Boolean);
        const newWords = words.slice(lastProcessedWordCount);

        if (options.commit) {
            liveTranscriptBuffer = cleaned;
            lastProcessedWordCount = words.length;
        }

        return newWords;
    }

    function markCurrentWordCorrect() {
        if (currentChunkIndex >= getCurrentChunks().length) return;

        wordStates[currentChunkIndex] = 'correct';
        correctChunks++;
        pendingWrongWord = null;
        pendingWrongCount = 0;
        pendingSpokenWords = [];
        renderChunks();
    }

    function markCurrentWordWrong(spokenWord) {
        if (currentChunkIndex >= getCurrentChunks().length) return;

        if (wordStates[currentChunkIndex] !== 'wrong') wrongChunks++;

        wordStates[currentChunkIndex] = 'wrong';
        pendingWrongWord = null;
        pendingWrongCount = 0;
        pendingSpokenWords = [];
        renderChunks();
        activateChunk(currentChunkIndex);
        updateStats();
        updateTracker();
        setStatus('Try this word again');
    }

    function advanceToNextWord() {
        currentChunkIndex++;
        activateChunk(currentChunkIndex);
        updateStats();
        updateTracker();
        completeAyahIfDone();
    }

    function completeAyahIfDone() {
        const chunks = getCurrentChunks();
        if (currentChunkIndex < chunks.length) return;

        completedAyahs.add(currentAyahIndex);
        renderAyahMap();
        updateStats();
        updateTracker();
        setStatus('Ayah complete');

        if (autoMoveNextAyah && currentAyahIndex < ayahs.length - 1 && !completionMoveTimer) {
            completionMoveTimer = setTimeout(() => {
                completionMoveTimer = null;
                loadAyah(currentAyahIndex + 1);

                if (isListening) {
                    setStatus('Live listening... recite now');
                    activateChunk(0);
                }
            }, autoMoveDelayMs);
        }
    }

    function processLiveWords(words, options = {}) {
        const chunks = getCurrentChunks();
        const isFinal = Boolean(options.isFinal);

        words.forEach(spokenWord => {
            if (currentChunkIndex >= chunks.length) return;

            const expectedWord = chunks[currentChunkIndex];
            pendingSpokenWords.push(spokenWord);
            const pendingPhrase = pendingSpokenWords.join(' ');
            const pendingLetterPhrase = normalizeSpokenLetterPhrase(pendingSpokenWords);
            const expectedNormalized = normalizeArabic(expectedWord);

            if (matchExpectedWord(pendingPhrase, expectedWord) || matchExpectedWord(spokenWord, expectedWord)) {
                markCurrentWordCorrect();
                advanceToNextWord();
                return;
            }

            if (
                pendingLetterPhrase !== null &&
                expectedNormalized.startsWith(pendingLetterPhrase) &&
                pendingLetterPhrase.length < expectedNormalized.length &&
                pendingSpokenWords.length < Math.min(5, expectedNormalized.length)
            ) {
                return;
            }

            if (!isFinal && isPartialExpectedWord(spokenWord, expectedWord)) {
                setStatus('Listening for full word...');
                return;
            }

            if (pendingWrongWord === pendingPhrase) pendingWrongCount++;
            else {
                pendingWrongWord = pendingPhrase;
                pendingWrongCount = 1;
            }

            if (isFinal || pendingWrongCount >= wrongConfirmThreshold) {
                markCurrentWordWrong(pendingPhrase);
            }
        });
    }

    function setupLiveSpeechRecognition() {
        const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!Recognition) return false;

        speechRecognition = new Recognition();
        speechRecognition.lang = 'ar-SA';
        speechRecognition.continuous = true;
        speechRecognition.interimResults = true;
        speechRecognition.maxAlternatives = 1;
        speechRecognition.onresult = handleLiveSpeechResult;

        speechRecognition.onend = function () {
            if (!isListening) return;

            try {
                speechRecognition.start();
            } catch (error) {
                setStatus('Live transcript paused. Tap mic to restart.');
            }
        };

        speechRecognition.onerror = function () {
            if (isListening) setStatus('Live transcript needs clear audio. Keep reciting.');
        };

        return true;
    }

    function handleLiveSpeechResult(event) {
        let interimText = '';
        let finalText = '';

        for (let i = event.resultIndex; i < event.results.length; i++) {
            const transcript = event.results[i][0].transcript || '';

            if (event.results[i].isFinal) finalText += transcript + ' ';
            else interimText += transcript + ' ';
        }

        const finalPreview = keepArabicClient(finalText);
        const interimPreview = keepArabicClient(interimText);
        const visibleText = keepArabicClient(`${liveTranscriptBuffer} ${finalPreview} ${interimPreview}`);

        if (transcriptBox) transcriptBox.textContent = visibleText || '...';

        if (finalText) {
            const words = extractNewArabicWords(`${liveTranscriptBuffer} ${finalText}`, { commit: true });

            if (words.length) {
                liveSpokenWords.push(...words);
                lastInterimSignature = '';
                processLiveWords(words, { isFinal: true });
            } else if (pendingWrongWord) {
                processLiveWords([pendingWrongWord], { isFinal: true });
            }
        }

        if (interimText) {
            const words = extractNewArabicWords(`${liveTranscriptBuffer} ${interimText}`);
            const interimSignature = words.join('|');

            if (words.length && interimSignature !== lastInterimSignature) {
                lastInterimSignature = interimSignature;
                processLiveWords(words, { isFinal: false });
            }
        }
    }

    function startOptionalRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) return;

        navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            }
        }).then(stream => {
            mediaStream = stream;
            const mimeType = getSupportedMimeType();
            const options = mimeType ? { mimeType } : {};

            audioChunks = [];
            mediaRecorder = new MediaRecorder(mediaStream, options);

            mediaRecorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) audioChunks.push(event.data);
            };

            mediaRecorder.onstop = function () {
                stopTracks();
            };

            mediaRecorder.start();
        }).catch(() => {
            setStatus('Mic recording unavailable, live transcript may still work.');
        });
    }

    function startLiveChecking() {
        if (!speechRecognition && !setupLiveSpeechRecognition()) {
            setStatus('Live transcript requires Chrome/Edge. Backend transcription can be used only as slower fallback.');
            return;
        }

        try {
            isListening = true;
            if (micBtn) micBtn.classList.add('listening');
            liveTranscriptBuffer = '';
            lastProcessedWordCount = 0;
            pendingWrongWord = null;
            pendingWrongCount = 0;
            pendingSpokenWords = [];
            liveSpokenWords = [];
            lastInterimSignature = '';
            updateTracker();
            activateChunk(currentChunkIndex);
            setStatus('Live listening... recite now');
            startOptionalRecording();
            speechRecognition.start();
        } catch (error) {
            isListening = false;
            if (micBtn) micBtn.classList.remove('listening');
            updateTracker();
            setStatus('Live transcript requires Chrome/Edge. Backend transcription can be used only as slower fallback.');
        }
    }

    function stopLiveChecking() {
        isListening = false;

        if (speechRecognition) {
            try {
                speechRecognition.stop();
            } catch (error) {
                // Recognition may already be stopped by the browser.
            }
        }

        if (micBtn) micBtn.classList.remove('listening');

        if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop();
        else stopTracks();

        updateTracker();
        setStatus('Ready');
    }

    function getSupportedMimeType() {
        if (!window.MediaRecorder) return '';

        const preferredTypes = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/ogg;codecs=opus'
        ];

        return preferredTypes.find(type => MediaRecorder.isTypeSupported(type)) || '';
    }

    function stopTracks() {
        if (mediaStream) {
            mediaStream.getTracks().forEach(track => track.stop());
            mediaStream = null;
        }
    }

    if (surahSelect) {
        surahSelect.addEventListener('change', function () {
            if (this.value) window.location.href = `/memorize-quran/${this.value}`;
        });
    }

    if (ayahSelect) {
        ayahSelect.addEventListener('change', function () {
            const ayahNumber = Math.max(1, Number(this.value) || 1);
            loadAyah(ayahNumber - 1);
        });
    }

    if (prevAyahBtn) prevAyahBtn.addEventListener('click', () => loadAyah(currentAyahIndex - 1));
    if (nextAyahBtn) nextAyahBtn.addEventListener('click', () => loadAyah(currentAyahIndex + 1));
    if (resetBtn) resetBtn.addEventListener('click', resetCurrentAyah);
    if (revealBtn) revealBtn.addEventListener('click', revealCurrentChunk);

    if (manualBtn) {
        manualBtn.addEventListener('click', function () {
            const words = normalizeArabic(manualInput ? manualInput.value : '').split(' ').filter(Boolean);
            processLiveWords(words, { isFinal: true });

            if (manualInput) {
                manualInput.value = '';
                manualInput.focus();
            }
        });
    }

    if (manualInput) {
        manualInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const words = normalizeArabic(this.value).split(' ').filter(Boolean);
                processLiveWords(words, { isFinal: true });
                this.value = '';
            }
        });
    }

    if (micBtn) {
        micBtn.addEventListener('click', function () {
            if (isListening) stopLiveChecking();
            else startLiveChecking();
        });
    }

    if (ayahs.length) loadAyah(currentAyahIndex);
});
</script>
@endpush
