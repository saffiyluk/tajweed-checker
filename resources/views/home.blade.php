@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="container py-4">
    <!-- Hero Section -->
    <div class="hero-section mb-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h1 class="hero-title mb-4">
                    Master Tajweed with<br>
                    <span class="gradient-text">AI-Powered Feedback</span>
                </h1>
                <p class="hero-description mb-4">
                    Record or upload your Quranic recitation and get instant, detailed feedback 
                    on your Tajweed pronunciation. Perfect your recitation with intelligent analysis.
                </p>
                <div class="hero-actions">
                    <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="btn btn-primary btn-lg">
                        <i class="fas fa-volume-down me-2"></i>Ikhfa Haqiqi
                    </a>
                    <a href="{{ route('tajweed.izhar-halqi') }}" class="btn btn-success btn-lg">
                        <i class="fas fa-volume-up me-2"></i>Izhar Halqi
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-visual">
                    <div class="quran-icon">
                        <i class="fas fa-quran"></i>
                    </div>
                    <div class="visual-content text-center">
                        <h5 class="mb-2">Quranic Excellence</h5>
                        <p class="text-muted">Learn the correct way to recite the Quran</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="features-section mb-5">
        <div class="section-header mb-4">
            <h2 class="section-title">Key Features</h2>
            <p class="section-subtitle">Everything you need to perfect your Tajweed</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-microphone"></i>
                </div>
                <div class="feature-content">
                    <h5 class="feature-title">Easy Recording</h5>
                    <p class="feature-description">Record directly from your browser or upload audio files with one click</p>
                </div>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="feature-content">
                    <h5 class="feature-title">AI Analysis</h5>
                    <p class="feature-description">Advanced machine learning analyzes your pronunciation accuracy</p>
                </div>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="feature-content">
                    <h5 class="feature-title">Instant Feedback</h5>
                    <p class="feature-description">Get detailed feedback and personalized improvement suggestions</p>
                </div>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="feature-content">
                    <h5 class="feature-title">Track Progress</h5>
                    <p class="feature-description">View your recitation history and monitor improvement over time</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-section mb-5">
        <div class="section-header mb-4">
            <h2 class="section-title">Your Learning Journey</h2>
            <p class="section-subtitle">Track your progress and achievements</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-music"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number">{{ auth()->user()->audioRecitations()->count() ?? 0 }}</h3>
                    <p class="stat-label">Total Recitations</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number">
                        {{ auth()->user()->audioRecitations()
                            ->whereHas('analysisResult', fn($q) => $q->where('correctness', 'correct'))
                            ->count() ?? 0 }}
                    </h3>
                    <p class="stat-label">Correct Pronunciations</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 class="stat-number">
                        {{ auth()->user()->audioRecitations()
                            ->whereHas('analysisResult', fn($q) => $q->where('correctness', 'incorrect'))
                            ->count() ?? 0 }}
                    </h3>
                    <p class="stat-label">Needs Improvement</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="cta-section mb-5">
        <div class="cta-card">
            <div class="cta-content">
                <h3 class="cta-title">Your History Recitation is here</h3>
                <p class="cta-description">
                    Start recording today and get instant feedback on your recitation.
                    Perfect your pronunciation with every session.
                </p>
                <div class="cta-actions">
                    <a href="{{ route('tajweed.history') }}" class="btn btn-light">
                        <i class="fas fa-history me-2"></i>View My Recitations
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tajweed Rules Section -->
    <div class="rules-section">
        <div class="section-header mb-4">
            <h2 class="section-title">Learn Tajweed Rules</h2>
            <p class="section-subtitle">Master the rules of beautiful Quranic recitation</p>
        </div>
        
        <div class="rules-grid">
            <a href="{{ route('tajweed.ikhfa-haqiqi') }}" class="rule-card ikhfa-card">
                <div class="rule-icon">
                    <i class="fas fa-volume-down"></i>
                </div>
                <div class="rule-content">
                    <h5 class="rule-title">Ikhfa Haqiqi</h5>
                    <p class="rule-description">
                        True concealment of noon sakinah and tanween when followed by one of the 15 Ikhfa letters.
                    </p>
                    <span class="rule-link">Learn More <i class="fas fa-arrow-right ms-1"></i></span>
                </div>
            </a>
            
            <a href="{{ route('tajweed.izhar-halqi') }}" class="rule-card izhar-card">
                <div class="rule-icon">
                    <i class="fas fa-volume-up"></i>
                </div>
                <div class="rule-content">
                    <h5 class="rule-title">Izhar Halqi</h5>
                    <p class="rule-description">
                        Clear pronunciation of noon sakinah and tanween when followed by one of the 6 throat letters.
                    </p>
                    <span class="rule-link">Learn More <i class="fas fa-arrow-right ms-1"></i></span>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
:root {
    --primary: #2563eb;
    --primary-light: #3b82f6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --gray: #64748b;
    --border: #e2e8f0;
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
    --radius: 12px;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
}

/* Hero Section */
.hero-section {
    padding: 3rem 0;
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
    margin-bottom: 1.5rem;
}

.gradient-text {
    background: linear-gradient(135deg, var(--primary), var(--success));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-description {
    font-size: 1.25rem;
    color: var(--gray);
    line-height: 1.6;
    margin-bottom: 2rem;
}

.hero-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.hero-actions .btn {
    padding: 1rem 2rem;
    font-weight: 600;
    border-radius: var(--radius);
    transition: all 0.3s ease;
}

.hero-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.hero-visual {
    background: white;
    border-radius: var(--radius);
    padding: 3rem 2rem;
    box-shadow: var(--shadow);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hero-visual::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success));
}

.quran-icon {
    font-size: 4rem;
    color: var(--primary);
    margin-bottom: 1.5rem;
}

.visual-content h5 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

/* Section Header */
.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.section-subtitle {
    font-size: 1.125rem;
    color: var(--gray);
    max-width: 600px;
    margin: 0 auto;
}

/* Features Section */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.feature-card {
    background: white;
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    border: 1px solid transparent;
    text-align: center;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.feature-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    margin: 0 auto 1.5rem;
}

.feature-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
}

.feature-description {
    color: var(--gray);
    font-size: 0.9375rem;
    line-height: 1.6;
    margin: 0;
}

/* Stats Section */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: var(--light);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--primary);
    flex-shrink: 0;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
    line-height: 1;
}

.stat-label {
    color: var(--gray);
    font-size: 0.9375rem;
    margin: 0.5rem 0 0;
}

/* CTA Section */
.cta-card {
    background: linear-gradient(135deg, var(--dark), #2d3748);
    border-radius: var(--radius);
    padding: 4rem 2rem;
    text-align: center;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}

.cta-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success));
}

.cta-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    margin-bottom: 1rem;
}

.cta-description {
    font-size: 1.125rem;
    color: rgba(255, 255, 255, 0.9);
    max-width: 600px;
    margin: 0 auto 2rem;
    line-height: 1.6;
}

.cta-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.cta-actions .btn {
    padding: 0.875rem 2rem;
    font-weight: 600;
    border-radius: var(--radius);
    transition: all 0.3s ease;
}

.cta-actions .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.cta-actions .btn-light {
    background: white;
    color: var(--dark);
    border: none;
}

.cta-actions .btn-outline-light {
    background: transparent;
    color: white;
    border: 2px solid white;
}

.cta-actions .btn-outline-light:hover {
    background: white;
    color: var(--dark);
}

/* Rules Section */
.rules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.rule-card {
    background: white;
    border-radius: var(--radius);
    padding: 2rem;
    box-shadow: var(--shadow);
    transition: all 0.3s ease;
    text-decoration: none;
    display: block;
    border: 2px solid transparent;
}

.rule-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    text-decoration: none;
}

.ikhfa-card:hover {
    border-color: var(--primary);
}

.izhar-card:hover {
    border-color: var(--success);
}

.rule-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
}

.ikhfa-card .rule-icon {
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary);
}

.izhar-card .rule-icon {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.rule-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 1rem;
}

.rule-description {
    color: var(--gray);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.rule-link {
    color: var(--primary);
    font-weight: 600;
    font-size: 0.9375rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
}

.ikhfa-card .rule-link {
    color: var(--primary);
}

.izhar-card .rule-link {
    color: var(--success);
}

.rule-card:hover .rule-link {
    transform: translateX(5px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2.5rem;
        text-align: center;
    }
    
    .hero-description {
        text-align: center;
    }
    
    .hero-actions {
        justify-content: center;
    }
    
    .features-grid,
    .stats-grid,
    .rules-grid {
        grid-template-columns: 1fr;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .cta-title {
        font-size: 2rem;
    }
    
    .cta-actions {
        flex-direction: column;
    }
    
    .cta-actions .btn {
        width: 100%;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 2rem;
    }
    
    .section-title {
        font-size: 1.75rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .hero-actions .btn {
        width: 100%;
    }
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.hero-section,
.features-section,
.stats-section,
.cta-section,
.rules-section {
    animation: fadeIn 0.6s ease-out;
}

.hero-section {
    animation-delay: 0.1s;
}

.features-section {
    animation-delay: 0.2s;
}

.stats-section {
    animation-delay: 0.3s;
}

.cta-section {
    animation-delay: 0.4s;
}

.rules-section {
    animation-delay: 0.5s;
}

/* Loading State */
.stat-number,
.feature-card,
.rule-card {
    transition: opacity 0.3s ease;
}
</style>

<script>
// Add subtle animations on scroll
document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    // Observe all cards
    document.querySelectorAll('.feature-card, .stat-card, .rule-card').forEach(card => {
        observer.observe(card);
    });

    // Update stats with animation
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        let currentValue = 0;
        const increment = finalValue / 50;
        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                currentValue = finalValue;
                clearInterval(timer);
            }
            stat.textContent = Math.floor(currentValue);
        }, 30);
    });
});
</script>
@endsection