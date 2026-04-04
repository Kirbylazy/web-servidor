<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dario Aguilar Rodriguez | Desarrollador Web</title>
    <link rel="icon" type="image/png" href="/favicon.php">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:        #080b12;
            --bg2:       #0f1320;
            --bg3:       #151b2e;
            --green:     #39ff14;
            --cyan:      #00e5ff;
            --purple:    #a855f7;
            --orange:    #ff6b35;
            --text:      #c9d1e0;
            --text-dim:  #6b7a99;
            --border:    #1e2a45;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'VT323', monospace;
            font-size: 18px;
            line-height: 1.6;
            overflow-x: hidden;
        }

        body::after {
            content: '';
            position: fixed; inset: 0;
            background: repeating-linear-gradient(
                0deg, transparent, transparent 2px,
                rgba(0,0,0,0.07) 2px, rgba(0,0,0,0.07) 4px
            );
            pointer-events: none; z-index: 9999;
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; width: 100%; z-index: 100;
            background: rgba(8,11,18,0.93);
            backdrop-filter: blur(6px);
            border-bottom: 2px solid var(--green);
            padding: 12px 32px;
            display: flex; align-items: center; justify-content: space-between;
        }
        nav .logo {
            font-family: 'Press Start 2P', monospace;
            font-size: 11px; color: var(--green);
            text-shadow: 0 0 10px var(--green);
        }
        nav ul { list-style: none; display: flex; gap: 20px; flex-wrap: wrap; }
        nav ul a {
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--text-dim);
            text-decoration: none; transition: color 0.2s;
        }
        nav ul a:hover { color: var(--green); text-shadow: 0 0 8px var(--green); }

        /* ── HERO ── */
        #hero {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 120px 48px 60px;
            position: relative;
            background:
                radial-gradient(ellipse 70% 50% at 30% 60%, rgba(0,229,255,0.04) 0%, transparent 70%),
                radial-gradient(ellipse 40% 30% at 80% 20%, rgba(168,85,247,0.05) 0%, transparent 60%);
        }
        #hero .pixel-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 40px 40px; opacity: 0.25;
        }
        #hero .hero-inner {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 64px;
            align-items: center;
            width: 100%; max-width: 1000px;
            position: relative; z-index: 1;
        }
        #hero .hero-text { display: flex; flex-direction: column; align-items: flex-start; }
        #hero .badge {
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--cyan);
            border: 2px solid var(--cyan);
            padding: 6px 14px; margin-bottom: 28px;
            display: inline-block;
            box-shadow: 0 0 12px rgba(0,229,255,0.25);
            animation: pulse-cyan 2s ease-in-out infinite;
            line-height: 1.8;
        }
        @keyframes pulse-cyan {
            0%,100% { box-shadow: 0 0 12px rgba(0,229,255,0.25); }
            50%      { box-shadow: 0 0 28px rgba(0,229,255,0.55); }
        }
        @keyframes pulse-green {
            0%,100% { box-shadow: 0 0 12px rgba(57,255,20,0.3); }
            50%      { box-shadow: 0 0 28px rgba(57,255,20,0.6); }
        }
        #hero h1 {
            font-family: 'Press Start 2P', monospace;
            font-size: clamp(20px, 4vw, 46px);
            color: #fff; line-height: 1.5;
            text-shadow: 0 0 40px rgba(0,229,255,0.15);
            margin-bottom: 18px;
        }
        #hero h1 span { color: var(--cyan); }
        #hero .role {
            font-family: 'Press Start 2P', monospace;
            font-size: clamp(9px, 1.6vw, 13px);
            color: var(--green);
            margin-bottom: 18px;
            text-shadow: 0 0 12px rgba(57,255,20,0.4);
        }
        #hero .cursor {
            display: inline-block; color: var(--green);
            animation: blink 1s step-end infinite;
        }
        @keyframes blink { 0%,100% { opacity:1; } 50% { opacity:0; } }
        #hero .tagline {
            font-size: 20px; color: var(--text-dim);
            margin-bottom: 40px;
        }

        /* ── AVATAR ── */
        .avatar-wrap {
            position: relative; flex-shrink: 0;
        }
        .avatar-wrap::before {
            content: '// PLAYER_1';
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--green);
            display: block; text-align: center;
            margin-bottom: 10px;
            opacity: 0.7;
        }
        .avatar-frame {
            position: relative;
            border: 3px solid var(--green);
            box-shadow:
                0 0 0 1px var(--bg),
                0 0 0 4px rgba(57,255,20,0.2),
                8px 8px 0 0 var(--cyan),
                0 0 40px rgba(57,255,20,0.15);
            display: inline-block;
        }
        .avatar-frame img {
            display: block;
            width: 220px;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }
        .avatar-wrap::after {
            content: '';
            position: absolute;
            bottom: -14px; left: 8px;
            width: calc(100% - 8px); height: 14px;
            background: rgba(0,229,255,0.15);
            filter: blur(8px);
        }

        .btn {
            font-family: 'Press Start 2P', monospace;
            font-size: 9px; padding: 14px 26px;
            border: 2px solid var(--green); color: var(--green);
            background: transparent; text-decoration: none;
            display: inline-block; cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            background: var(--green); color: var(--bg);
            box-shadow: 0 0 20px rgba(57,255,20,0.5);
        }
        .btn-outline {
            border-color: var(--cyan); color: var(--cyan); margin-left: 12px;
        }
        .btn-outline:hover {
            background: var(--cyan); color: var(--bg);
            box-shadow: 0 0 20px rgba(0,229,255,0.5);
        }

        /* ── LAYOUT ── */
        .section-wrap { padding: 80px 24px; max-width: 1000px; margin: 0 auto; }
        .full-bg { background: var(--bg2); }
        .full-bg-3 { background: var(--bg3); }

        .section-title {
            font-family: 'Press Start 2P', monospace;
            font-size: clamp(10px, 1.8vw, 15px);
            color: var(--green); margin-bottom: 48px;
            display: flex; align-items: center; gap: 12px;
        }
        .section-title::after {
            content: ''; flex: 1; height: 2px;
            background: linear-gradient(90deg, var(--green), transparent);
        }

        /* ── ABOUT ── */
        .about-grid { display: grid; grid-template-columns: 3fr 2fr; gap: 40px; align-items: start; }
        .about-text { font-size: 20px; line-height: 1.75; }
        .about-text p { margin-bottom: 16px; color: var(--text); }
        .about-text strong { color: var(--cyan); }

        .info-box {
            background: var(--bg3); border: 2px solid var(--border);
            padding: 24px; display: flex; flex-direction: column; gap: 16px;
        }
        .info-row { display: flex; flex-direction: column; gap: 4px; }
        .info-label {
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--text-dim);
        }
        .info-value { font-size: 18px; color: var(--green); }

        /* ── SKILLS ── */
        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 20px;
        }
        .skill-category {
            background: var(--bg2); border: 2px solid var(--border);
            padding: 24px; transition: border-color 0.25s, transform 0.2s;
        }
        .skill-category:hover { border-color: var(--cyan); transform: translateY(-2px); }
        .skill-cat-title {
            font-family: 'Press Start 2P', monospace;
            font-size: 8px; color: var(--cyan);
            margin-bottom: 18px; line-height: 1.8;
        }
        .skill-list { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-item {
            font-size: 17px; color: var(--text);
            background: var(--bg3); border: 1px solid var(--border);
            padding: 3px 12px; transition: all 0.2s; cursor: default;
        }
        .skill-item:hover { border-color: var(--green); color: var(--green); }

        /* ── EXPERIENCE ── */
        .exp-card {
            background: var(--bg3); border: 2px solid var(--border);
            border-left: 4px solid var(--cyan);
            padding: 32px; transition: border-color 0.2s, box-shadow 0.2s;
        }
        .exp-card:hover {
            border-color: var(--cyan);
            box-shadow: 0 0 24px rgba(0,229,255,0.08);
        }
        .exp-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .exp-title {
            font-family: 'Press Start 2P', monospace;
            font-size: 10px; color: #fff; line-height: 1.7;
        }
        .exp-company { font-size: 21px; color: var(--cyan); margin-top: 6px; }
        .exp-date {
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--purple);
            border: 1px solid var(--purple); padding: 4px 10px;
            white-space: nowrap; height: fit-content;
        }
        .current-badge {
            font-family: 'Press Start 2P', monospace;
            font-size: 6px; color: var(--green);
            border: 1px solid var(--green); padding: 3px 8px;
            animation: pulse-green 2s ease-in-out infinite;
            display: inline-block; margin-top: 4px;
        }
        .exp-desc { font-size: 19px; color: var(--text); line-height: 1.7; margin-bottom: 16px; }
        .exp-tags { display: flex; flex-wrap: wrap; gap: 8px; }
        .exp-tag {
            font-family: 'Press Start 2P', monospace;
            font-size: 6px; padding: 3px 8px;
            color: var(--orange); border: 1px solid rgba(255,107,53,0.4);
            background: rgba(255,107,53,0.05);
        }

        /* ── EDUCATION ── */
        .edu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .edu-card {
            background: var(--bg2); border: 2px solid var(--border);
            padding: 28px; transition: border-color 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .edu-card:hover { border-color: var(--cyan); box-shadow: 0 0 20px rgba(0,229,255,0.08); }
        .edu-card.featured { border-color: rgba(0,229,255,0.4); }
        .edu-card .icon { font-size: 30px; margin-bottom: 14px; display: block; }
        .edu-card .degree {
            font-family: 'Press Start 2P', monospace;
            font-size: 8px; color: var(--cyan);
            margin-bottom: 10px; line-height: 1.9;
        }
        .edu-card .school { font-size: 19px; color: var(--green); margin-bottom: 4px; }
        .edu-card .year {
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--purple); margin-bottom: 14px;
        }
        .edu-card .edu-desc { font-size: 17px; color: var(--text); line-height: 1.65; }
        .edu-badge {
            font-family: 'Press Start 2P', monospace;
            font-size: 6px; padding: 3px 8px;
            border: 1px solid var(--green); color: var(--green);
            display: inline-block; margin-left: 8px;
            animation: pulse-green 2s ease-in-out infinite;
            vertical-align: middle;
        }
        .modules {
            display: flex; flex-wrap: wrap; gap: 6px; margin-top: 14px;
        }
        .module {
            font-size: 14px; color: var(--text-dim);
            border: 1px solid var(--border); padding: 2px 8px;
        }

        /* ── PROJECTS ── */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        .project-card {
            background: var(--bg3); border: 2px solid var(--border);
            padding: 28px; text-decoration: none; display: block;
            transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
            position: relative; overflow: hidden;
        }
        .project-card::before {
            content: ''; position: absolute;
            top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, var(--cyan), var(--green));
            transform: scaleX(0); transform-origin: left; transition: transform 0.3s;
        }
        .project-card:hover { border-color: var(--cyan); transform: translateY(-4px); box-shadow: 0 8px 28px rgba(0,229,255,0.1); }
        .project-card:hover::before { transform: scaleX(1); }
        .project-langs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .project-lang {
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--orange);
            border: 1px solid var(--orange); padding: 3px 8px;
        }
        .project-card h3 {
            font-family: 'Press Start 2P', monospace;
            font-size: 10px; color: #fff;
            margin-bottom: 12px; line-height: 1.7;
        }
        .project-card p { font-size: 18px; color: var(--text); line-height: 1.65; }
        .project-card .arrow {
            margin-top: 20px; color: var(--cyan);
            font-family: 'Press Start 2P', monospace; font-size: 8px;
        }

        /* ── HOBBIES ── */
        .hobbies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .hobby-card {
            background: var(--bg3); border: 2px solid var(--border);
            padding: 28px; text-align: center;
            transition: border-color 0.2s, transform 0.2s;
        }
        .hobby-card:hover { border-color: var(--purple); transform: translateY(-3px); }
        .hobby-icon { font-size: 36px; display: block; margin-bottom: 14px; }
        .hobby-name {
            font-family: 'Press Start 2P', monospace;
            font-size: 8px; color: var(--purple); line-height: 1.7;
        }
        .hobby-desc { font-size: 17px; color: var(--text-dim); margin-top: 8px; }

        /* ── CONTACT ── */
        .contact-box {
            background: var(--bg3); border: 2px solid var(--green);
            padding: 48px 40px; max-width: 600px; margin: 0 auto;
            box-shadow: 0 0 60px rgba(57,255,20,0.04);
        }
        .contact-box .intro { font-size: 21px; color: var(--text); margin-bottom: 32px; line-height: 1.7; }
        .contact-links { display: flex; flex-direction: column; gap: 14px; }
        .contact-link {
            display: flex; align-items: center; justify-content: center; gap: 14px;
            font-family: 'Press Start 2P', monospace; font-size: 8px;
            color: var(--cyan); text-decoration: none; padding: 14px;
            border: 1px solid var(--border); transition: all 0.2s;
            line-height: 1.8;
        }
        .contact-link:hover { border-color: var(--cyan); color: #fff; background: rgba(0,229,255,0.04); }

        /* ── FOOTER ── */
        footer {
            text-align: center; padding: 32px 24px;
            border-top: 2px solid var(--border);
            font-family: 'Press Start 2P', monospace;
            font-size: 7px; color: var(--text-dim); line-height: 2.2;
        }
        footer span { color: var(--green); }

        /* ── FADE IN ── */
        .fade-in { opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
        .fade-in.visible { opacity: 1; transform: translateY(0); }

        /* ── RESPONSIVE ── */
        @media (max-width: 780px) {
            #hero { padding: 100px 24px 60px; }
            #hero .hero-inner {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 40px;
            }
            #hero .hero-text { align-items: center; }
            .avatar-wrap { display: flex; flex-direction: column; align-items: center; }
            .avatar-frame img { width: 160px; }
        }
        @media (max-width: 680px) {
            nav ul { gap: 10px; }
            nav ul a { font-size: 6px; }
            .about-grid { grid-template-columns: 1fr; }
            #hero h1 { font-size: 20px; }
            .contact-box { padding: 32px 20px; }
            .exp-header { flex-direction: column; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="logo">DAR.EXE</div>
    <ul>
        <li><a href="#sobre">SOBRE MÍ</a></li>
        <li><a href="#skills">SKILLS</a></li>
        <li><a href="#experiencia">EXPERIENCIA</a></li>
        <li><a href="#formacion">FORMACIÓN</a></li>
        <li><a href="#proyectos">PROYECTOS</a></li>
        <li><a href="#contacto">CONTACTO</a></li>
    </ul>
</nav>

<!-- HERO -->
<section id="hero">
    <div class="pixel-grid"></div>
    <div class="hero-inner">
        <div class="hero-text">
            <div class="badge">// TÉCNICO SUPERIOR EN DESARROLLO DE APLICACIONES WEB · 2026</div>
            <h1>DARIO <span>AGUILAR</span><br>RODRIGUEZ</h1>
            <div class="role">DESARROLLADOR WEB<span class="cursor">_</span></div>
            <p class="tagline">Frontend · Backend · Bases de datos · Despliegue</p>
            <div>
                <a href="#proyectos" class="btn">VER PROYECTOS</a>
                <a href="#contacto" class="btn btn-outline">CONTACTO</a>
            </div>
        </div>
        <div class="avatar-wrap">
            <div class="avatar-frame">
                <img src="/avatar.png" alt="Dario Aguilar Rodriguez">
            </div>
        </div>
    </div>
</section>

<!-- SOBRE MÍ -->
<div class="full-bg" id="sobre">
<div class="section-wrap">
    <h2 class="section-title">&gt; SOBRE_MÍ</h2>
    <div class="about-grid fade-in">
        <div class="about-text">
            <p>Soy desarrollador web con el <strong>G.S. en Desarrollo de Aplicaciones Web</strong> finalizando en 2026. Me apasiona construir aplicaciones bien hechas: código limpio, buena estructura y atención al detalle tanto en frontend como en backend.</p>
            <p>Vengo de un perfil técnico fuerte — trabajo actualmente en el <strong>laboratorio de Ingeniería en Fusión Nuclear de la Universidad de Sevilla</strong>, donde diseño y desarrollo soluciones a problemas complejos. Esa mentalidad de ingeniería me hace abordar los retos de desarrollo de forma metódica y orientada a resultados.</p>
            <p>Busco mi primera oportunidad como desarrollador en un equipo donde seguir creciendo, aportar desde el primer día, y construir cosas que importen.</p>
        </div>
        <div class="info-box fade-in">
            <div class="info-row">
                <span class="info-label">ESTADO</span>
                <span class="info-value">Buscando empleo en desarrollo</span>
            </div>
            <div class="info-row">
                <span class="info-label">UBICACIÓN</span>
                <span class="info-value">Sevilla, España</span>
            </div>
            <div class="info-row">
                <span class="info-label">DISPONIBILIDAD</span>
                <span class="info-value">Inmediata</span>
            </div>
            <div class="info-row">
                <span class="info-label">MODALIDAD</span>
                <span class="info-value">Presencial / Remoto / Híbrido</span>
            </div>
            <div class="info-row">
                <span class="info-label">CONTACTO</span>
                <span class="info-value">darioaguilarrodriguez88@gmail.com</span>
            </div>
        </div>
    </div>
</div>
</div>

<!-- SKILLS -->
<section id="skills">
    <h2 class="section-title">&gt; HABILIDADES_TÉCNICAS</h2>
    <div class="skills-grid">

        <div class="skill-category fade-in">
            <div class="skill-cat-title">◈ FRONTEND</div>
            <div class="skill-list">
                <span class="skill-item">HTML5</span>
                <span class="skill-item">CSS3</span>
                <span class="skill-item">JavaScript</span>
                <span class="skill-item">DOM</span>
                <span class="skill-item">AJAX</span>
                <span class="skill-item">Responsive Design</span>
                <span class="skill-item">UI / UX</span>
                <span class="skill-item">Accesibilidad Web</span>
            </div>
        </div>

        <div class="skill-category fade-in">
            <div class="skill-cat-title">◈ BACKEND</div>
            <div class="skill-list">
                <span class="skill-item">PHP</span>
                <span class="skill-item">APIs REST</span>
                <span class="skill-item">Servicios Web</span>
                <span class="skill-item">Arquitectura MVC</span>
                <span class="skill-item">Seguridad Web</span>
                <span class="skill-item">Aplicaciones híbridas</span>
            </div>
        </div>

        <div class="skill-category fade-in">
            <div class="skill-cat-title">◈ BASES DE DATOS</div>
            <div class="skill-list">
                <span class="skill-item">SQL</span>
                <span class="skill-item">MySQL</span>
                <span class="skill-item">Modelo relacional</span>
                <span class="skill-item">Procedimientos almacenados</span>
                <span class="skill-item">XML / XQuery</span>
            </div>
        </div>

        <div class="skill-category fade-in">
            <div class="skill-cat-title">◈ DESPLIEGUE & SISTEMAS</div>
            <div class="skill-list">
                <span class="skill-item">Linux</span>
                <span class="skill-item">Apache / Nginx</span>
                <span class="skill-item">Redes TCP/IP</span>
                <span class="skill-item">SSH / FTP</span>
                <span class="skill-item">Servidor propio</span>
                <span class="skill-item">Raspberry Pi</span>
            </div>
        </div>

        <div class="skill-category fade-in">
            <div class="skill-cat-title">◈ HERRAMIENTAS & METODOLOGÍA</div>
            <div class="skill-list">
                <span class="skill-item">Git / GitHub</span>
                <span class="skill-item">Webhooks / CI</span>
                <span class="skill-item">UML</span>
                <span class="skill-item">Testing</span>
                <span class="skill-item">Control de versiones</span>
                <span class="skill-item">Documentación técnica</span>
            </div>
        </div>

        <div class="skill-category fade-in">
            <div class="skill-cat-title">◈ ACTITUDES</div>
            <div class="skill-list">
                <span class="skill-item">Mentalidad técnica</span>
                <span class="skill-item">Resolución de problemas</span>
                <span class="skill-item">Trabajo en equipo</span>
                <span class="skill-item">Aprendizaje continuo</span>
                <span class="skill-item">Iniciativa propia</span>
            </div>
        </div>

    </div>
</section>

<!-- EXPERIENCIA -->
<div class="full-bg" id="experiencia">
<div class="section-wrap">
    <h2 class="section-title">&gt; EXPERIENCIA</h2>
    <div class="exp-card fade-in">
        <div class="exp-header">
            <div>
                <div class="exp-title">TÉCNICO DE LABORATORIO — INGENIERÍA EN FUSIÓN NUCLEAR</div>
                <div class="exp-company">Universidad de Sevilla</div>
                <div class="current-badge">EN ACTIVO</div>
            </div>
            <div class="exp-date">JUL 2024 → HOY</div>
        </div>
        <p class="exp-desc">
            Desarrollo de soluciones técnicas para un proyecto de investigación puntero: diseño, prototipado y fabricación de componentes para un reactor de fusión nuclear. El trabajo exige análisis riguroso de requisitos, iteración rápida sobre prototipos y documentación precisa — habilidades directamente trasladables al desarrollo de software.
        </p>
        <div class="exp-tags">
            <span class="exp-tag">RESOLUCIÓN DE PROBLEMAS</span>
            <span class="exp-tag">DISEÑO TÉCNICO</span>
            <span class="exp-tag">PROTOTIPADO</span>
            <span class="exp-tag">DOCUMENTACIÓN</span>
            <span class="exp-tag">ENTORNO DE I+D</span>
        </div>
    </div>
</div>
</div>

<!-- FORMACIÓN -->
<section id="formacion">
    <h2 class="section-title">&gt; FORMACIÓN</h2>
    <div class="edu-grid">

        <div class="edu-card featured fade-in">
            <span class="icon">💻</span>
            <div class="degree">
                G.S. DESARROLLO DE APLICACIONES WEB
                <span class="edu-badge">EN CURSO</span>
            </div>
            <div class="school">Centro de Formación</div>
            <div class="year">2024 – 2026 · 2000h · EQF Nivel 5</div>
            <div class="edu-desc">Desarrollo completo de aplicaciones web: frontend, backend, bases de datos, despliegue y diseño de interfaces. Formación orientada al mercado laboral con prácticas en empresa.</div>
            <div class="modules">
                <span class="module">HTML · CSS · JS</span>
                <span class="module">PHP</span>
                <span class="module">SQL / MySQL</span>
                <span class="module">APIs REST</span>
                <span class="module">Despliegue Web</span>
                <span class="module">UI / UX</span>
                <span class="module">Git</span>
                <span class="module">Sistemas informáticos</span>
            </div>
        </div>

        <div class="edu-card fade-in">
            <span class="icon">⚙</span>
            <div class="degree">G.S. MECATRÓNICA INDUSTRIAL</div>
            <div class="school">I.E.S. El Arenal</div>
            <div class="year">2021 – 2023</div>
            <div class="edu-desc">Formación técnica con fuerte componente en electrónica, sistemas de control y programación industrial. Base analítica que complementa el perfil de desarrollo.</div>
        </div>

    </div>
</section>

<!-- PROYECTOS -->
<div class="full-bg" id="proyectos">
<div class="section-wrap">
    <h2 class="section-title">&gt; PROYECTOS</h2>
    <div class="projects-grid">

        <a href="/poker-sencillo/index.php" class="project-card fade-in">
            <div class="project-langs">
                <span class="project-lang">PHP</span>
                <span class="project-lang">HTML</span>
                <span class="project-lang">CSS</span>
            </div>
            <h3>POKER SENCILLO</h3>
            <p>Juego de poker completo desarrollado en PHP. Implementa lógica de manos, baraja completa, gestión de jugadores y ciclo de partida. Desplegado en servidor propio sobre Raspberry Pi con Apache.</p>
            <div class="arrow">VER PROYECTO →</div>
        </a>

        <div class="project-card fade-in" style="border-style: dashed; cursor: default;">
            <div class="project-langs">
                <span class="project-lang" style="border-color:var(--text-dim); color:var(--text-dim);">EN DESARROLLO</span>
            </div>
            <h3 style="color: var(--text-dim);">PRÓXIMO PROYECTO</h3>
            <p style="color: var(--text-dim);">Más proyectos en camino. Esta sección se irá actualizando conforme avance en el ciclo y desarrolle nuevas aplicaciones.</p>
            <div class="arrow" style="color: var(--text-dim);">PRÓXIMAMENTE →</div>
        </div>

    </div>
</div>
</div>

<!-- AFICIONES -->
<section id="aficiones">
    <h2 class="section-title">&gt; FUERA_DEL_CÓDIGO</h2>
    <div class="hobbies-grid">
        <div class="hobby-card fade-in">
            <span class="hobby-icon">🧗</span>
            <div class="hobby-name">ESCALADA Y ALPINISMO</div>
            <div class="hobby-desc">Deporte que entrena la cabeza tanto como el cuerpo. Vocal en la Federación Andaluza de Montaña.</div>
        </div>
        <div class="hobby-card fade-in">
            <span class="hobby-icon">🖨</span>
            <div class="hobby-name">IMPRESIÓN Y DISEÑO 3D</div>
            <div class="hobby-desc">Diseño piezas en CATIA V5 y las fabrico. Mismo ciclo que el desarrollo: idea → prototipo → iteración.</div>
        </div>
        <div class="hobby-card fade-in">
            <span class="hobby-icon">💻</span>
            <div class="hobby-name">DESARROLLO DE APPS</div>
            <div class="hobby-desc">Programar por hobby antes de hacerlo por profesión. Este portfolio corre en un servidor montado en casa.</div>
        </div>
    </div>
</section>

<!-- CONTACTO -->
<div class="full-bg" id="contacto">
<div class="section-wrap" style="text-align:center">
    <h2 class="section-title" style="justify-content:center">&gt; CONTACTO</h2>
    <div class="contact-box fade-in">
        <p class="intro">Busco mi primera oportunidad en una empresa de desarrollo. Si tienes un proyecto interesante o una oferta, escríbeme.</p>
        <div class="contact-links">
            <a href="mailto:darioaguilarrodriguez88@gmail.com" class="contact-link">
                ✉ &nbsp;darioaguilarrodriguez88@gmail.com
            </a>
            <a href="tel:+34625850459" class="contact-link">
                ☎ &nbsp;625 85 04 59
            </a>
        </div>
    </div>
</div>
</div>

<!-- FOOTER -->
<footer>
    <span>DARIO AGUILAR RODRIGUEZ</span> · DESARROLLADOR WEB · SEVILLA<br>
    PORTFOLIO DESPLEGADO EN SERVIDOR PROPIO · RASPBERRY PI · <?php echo date('Y'); ?>
</footer>

<script>
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(el => {
            if (el.isIntersecting) el.target.classList.add('visible');
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
</script>

</body>
</html>
