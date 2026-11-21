<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin Finder knowledge index.
 *
 * Teacher-language "needs" for each LMS Labs plugin, so a request such as "I need to mark
 * essays faster" finds AI Essay Grader even though the catalogue copy never says "mark".
 * Also holds short plain-English pitches, highlight lists for catalogue entries whose
 * feature list is only install metadata, and the matching YouTube playlist title on the
 * LMS Hosting Services channel.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local;

/**
 * Static knowledge used by {@see plugin_finder}.
 */
class finder_index {
    /** @var string The LMS Hosting Services YouTube channel handle. */
    public const CHANNEL = 'https://www.youtube.com/@LMSHostingServices';

    /**
     * Plugin playlists on the LMS Hosting Services channel: playlist title => playlist ID.
     * Copied from the channel's Playlists tab and verified on 21 November 2026.
     *
     * @return string[]
     */
    public static function playlist_ids(): array {
        return [
            'AI Content Creator - Moodle Activity' => 'PLfInMEqz0_Uk',
            'AI Course Builder - Moodle Plugin' => 'PLJodqHDTOFs8',
            'AI Course Format - Moodle Plugin' => 'PLKrI2QTpsZ0I',
            'AI Essay Grader - Moodle Quiz Report Plugin' => 'PLMVV9uEDCPW4',
            'AI Interactive Video - Moodle Plugin' => 'PLWTcWed-OYGc',
            'AI Knowledge Check - Moodle Activity Module' => 'PLAikAUMhn8qU',
            'AI Language Teacher - Moodle Plugin' => 'PLErDeLUf860U',
            'AI Language Writing & Speaking - Moodle Plugin' => 'PLTCmUvhK6ZBI',
            'AI Learning Activities - Moodle Module Activity' => 'PLPj8sKauWso8',
            'AI Quiz Remedial Learning - Moodle Plugin' => 'PLKp_j1AKv4oA',
            'AI Anatomy - Moodle Activity' => 'PLZEgwi8X9zwU',
            'AI Safety Hazard - Moodle Activity' => 'PLPSj02DaIgkE',
            'AI Science Experiment - Moodle Activity' => 'PLR79UCcBI5CM',
            'AI Soft Skills - Moodle Activity' => 'PLZC0rdGp6hMI',
            'AI Virtual Classroom - Moodle Activity' => 'PLBHHcIMDv9Bs',
            'AI Visual Learning - Moodle Activity' => 'PLfLJOZMXLXEg',
            'Beacon Reports - Moodle Plugin' => 'PLIY7G47CKtGA',
            'Certificate Pro - Moodle Plugin' => 'PLEESPaB3rtAU',
            'Client Portal - Moodle Plugin' => 'PLWOuG9i5k5Ks',
            'Custom Pages - Moodle Plugin' => 'PLJ5eYfXyZ7xM',
            'Email Students - Moodle Plugin' => 'PLQ71JR27yCj4',
            'Essay Guard - Moodle Plugin' => 'PLANwCkNeLAhs',
            'Evidence Checklist - Moodle Activity' => 'PLFKPPY5ILcaI',
            'Fast Grade - Moodle Plugin' => 'PLNvtEUN_Uv0U',
            'Label Diagram - Moodle Activity Module' => 'PLL4GJY2WnE1I',
            'LMS Meet - Moodle Plugin' => 'PLeF7H2i-OyWE',
            'Media Optimiser - Moodle Plugin' => 'PLZhyK2R3_qLQ',
            'Premium SaaS Theme - Moodle Plugin' => 'PLFhpAYg-8J0A',
            'Quick Links - Moodle Plugin' => 'PLReGHzy0eaDA',
            'RTO Compliance - Moodle Plugin' => 'PLSuhN3kjQv8c',
            'Speed Up - Moodle Tool' => 'PLQu9ddJMCVbY',
            'Vocabulary Mastery - Moodle Activity' => 'PLV24VXAQYJng',
            // Channel-wide playlists, for plugins without one of their own.
            'Moodle AI Plugins Tutorials | Step-by-Step Guides by LMS Labs' => 'PLOU8MT9bN2Po',
            'LMS Labs Overview & Getting Started' => 'PLBaEgRDHO59g',
        ];
    }

    /**
     * Synonym groups. Every word in a group is treated as the group's first word.
     *
     * @return string[][]
     */
    public static function synonyms(): array {
        return [
            ['grade', 'grading', 'grades', 'graded', 'mark', 'marks', 'marking', 'marked', 'assess', 'assessing',
                'assessment', 'assessments', 'score', 'scoring', 'evaluate', 'evaluating', 'correct', 'correcting'],
            ['essay', 'essays', 'written', 'writing', 'longanswer', 'extended', 'paragraph', 'paragraphs'],
            ['feedback', 'comments', 'comment', 'critique'],
            ['plagiarism', 'cheat', 'cheating', 'copied', 'copying', 'paste', 'pasting', 'pasted', 'integrity',
                'originality', 'chatgpt', 'gpt', 'aiwritten', 'detect', 'detection', 'detector', 'honest'],
            ['quiz', 'quizzes', 'test', 'tests', 'exam', 'exams', 'mcq', 'questions', 'question', 'multichoice'],
            ['video', 'videos', 'youtube', 'film', 'clip', 'clips', 'mp4', 'recording', 'recordings'],
            ['slide', 'slides', 'powerpoint', 'ppt', 'pptx', 'presentation', 'presentations', 'deck', 'slideshow'],
            ['report', 'reports', 'reporting', 'analytics', 'dashboard', 'dashboards', 'stats', 'statistics', 'insight',
                'insights', 'data'],
            ['progress', 'completion', 'complete', 'completed', 'tracking', 'track', 'monitor', 'monitoring'],
            ['student', 'students', 'learner', 'learners', 'pupil', 'pupils', 'trainee', 'trainees', 'participant',
                'participants', 'kids', 'class'],
            ['teacher', 'teachers', 'trainer', 'trainers', 'assessor', 'assessors', 'tutor', 'tutors', 'lecturer',
                'instructor', 'educator'],
            ['create', 'creating', 'build', 'building', 'make', 'making', 'generate', 'generating', 'author',
                'authoring', 'design', 'designing', 'write', 'produce'],
            ['content', 'lesson', 'lessons', 'material', 'materials', 'resource', 'resources', 'topic', 'topics',
                'module', 'modules', 'unit', 'units'],
            ['course', 'courses', 'program', 'programs', 'programme', 'qualification', 'qualifications'],
            ['interactive', 'engaging', 'engagement', 'gamified', 'game', 'games', 'fun', 'drag', 'activity',
                'activities'],
            ['voice', 'voiceover', 'narration', 'narrate', 'audio', 'speech', 'tts', 'listen', 'listening', 'read aloud'],
            ['language', 'languages', 'esl', 'efl', 'english', 'spanish', 'french', 'vocabulary', 'vocab', 'words',
                'pronunciation', 'speaking', 'grammar'],
            ['rto', 'asqa', 'vet', 'avetmiss', 'compliance', 'compliant', 'audit', 'audits', 'tga', 'standards'],
            ['certificate', 'certificates', 'cert', 'certs', 'award', 'awards', 'credential'],
            ['email', 'emails', 'mail', 'message', 'messages', 'notify', 'notification', 'notifications', 'remind',
                'reminder', 'reminders'],
            ['login', 'log in', 'signin', 'sign', 'password', 'passwords', 'authentication', 'auth', 'sso', '2fa',
                'mfa', 'twofactor'],
            ['brand', 'branding', 'branded', 'logo', 'logos', 'colour', 'colours', 'color', 'colors', 'look', 'style',
                'theme', 'themes', 'font', 'fonts', 'appearance'],
            ['pay', 'payment', 'payments', 'paid', 'sell', 'selling', 'sales', 'checkout', 'ecommerce', 'stripe',
                'paddle', 'price', 'fee', 'fees', 'revenue', 'monetise', 'monetize'],
            ['storage', 'disk', 'space', 'compress', 'compression', 'smaller', 'files', 'file', 'bandwidth', 'size'],
            ['speed', 'slow', 'slowly', 'sluggish', 'speedup', 'lag', 'laggy', 'loading', 'timeout', 'timeouts'],
            ['behind', 'falling', 'struggling', 'struggle', 'lagging', 'overdue', 'late', 'missing', 'atrisk'],
            ['enrol', 'enrols', 'enrolment', 'enrolments', 'enroll', 'enrollment', 'intake', 'intakes', 'cohort',
                'cohorts', 'group', 'groups'],
            ['safety', 'hazard', 'hazards', 'whs', 'ohs', 'ppe', 'risk', 'risks', 'workplace'],
            ['staff', 'employee', 'employees', 'workforce', 'department', 'departments', 'position', 'positions',
                'corporate', 'compliance training'],
            ['scenario', 'scenarios', 'roleplay', 'simulation', 'simulations', 'branching', 'decision', 'decisions'],
            ['science', 'experiment', 'experiments', 'lab', 'labs', 'physics', 'chemistry', 'biology'],
            ['anatomy', 'body', 'medical', 'nursing', 'health', 'muscles', 'bones', 'skeleton'],
            ['diagram', 'diagrams', 'label', 'labels', 'labelling', 'labeling', 'parts'],
            ['live', 'zoom', 'webinar', 'meeting', 'meetings', 'virtual', 'online class', 'classroom', 'breakout'],
            ['identity', 'id', 'verify', 'verification', 'photo', 'webcam', 'face'],
            ['rpl', 'prior', 'recognition'],
            ['evidence', 'observation', 'observations', 'checklist', 'checklists', 'practical', 'signature',
                'signatures'],
            ['pdf', 'word', 'docx', 'document', 'documents', 'doc', 'workbook', 'workbooks', 'worksheet',
                'worksheets'],
            ['help', 'support', 'stuck', 'howto', 'documentation'],
            ['revision', 'revise', 'remedial', 'remediation', 'wrong', 'mistakes', 'retry', 'reattempt'],
            ['map', 'mapping', 'mapped', 'gap', 'gaps', 'benchmark', 'benchmarks', 'rubric', 'rubrics', 'criteria'],
            ['ai', 'artificial', 'intelligence', 'automatic', 'automatically', 'auto', 'automate', 'automation'],
            ['portal', 'client', 'clients', 'customer', 'customers', 'manager', 'managers', 'employer', 'employers'],
            ['page', 'pages', 'homepage', 'frontpage', 'landing', 'html', 'website'],
            ['navigation', 'navigate', 'next', 'previous', 'arrows', 'menu', 'links', 'shortcuts'],
            ['scorm', 'rise', 'articulate', 'storyline'],
            ['recertify', 'recertification', 'expiry', 'expire', 'expires', 'renew', 'renewal', 'refresher', 'annual'],
            ['workshop', 'workshops', 'booking', 'bookings', 'attendance', 'venue', 'session', 'sessions', 'timetable'],
            ['prerequisite', 'prerequisites', 'gate', 'gating', 'unlock', 'locked', 'lock', 'restrict', 'restriction',
                'delay', 'delayed', 'drip'],
            ['suspend', 'suspension', 'suspended', 'deactivate', 'archive'],
            ['site', 'server', 'downtime', 'down', 'outage', 'crash', 'uptime', 'health'],
        ];
    }

    /**
     * Words that carry no meaning in a request.
     *
     * @return string[]
     */
    public static function stopwords(): array {
        return ['i', 'me', 'my', 'we', 'our', 'us', 'you', 'your', 'a', 'an', 'the', 'and', 'or', 'but', 'to',
            'of', 'for', 'in', 'on', 'at', 'by', 'with', 'from', 'that', 'this', 'these', 'those', 'it', 'its',
            'is', 'are', 'was', 'be', 'been', 'can', 'could', 'would', 'should', 'will', 'want', 'wants', 'need',
            'needs', 'needed', 'looking', 'look for', 'find', 'some', 'something', 'thing', 'things', 'way', 'ways',
            'plugin', 'plugins', 'moodle', 'tool', 'tools', 'app', 'help me', 'please', 'how', 'do', 'does', 'what',
            'which', 'who', 'all', 'any', 'more', 'better', 'easily', 'easy', 'so', 'also', 'like', 'have', 'has',
            'get', 'into', 'about', 'just', 'there', 'their', 'them', 'they', 'too', 'very', 'really', 'lot', 'lots',
            'kind', 'type', 'sort', 'am', 'im', 'help', 'using', 'use', 'able', 'let', 'lets', 'allow', 'allows',
            'show', 'shows', 'see', 'keep', 'keeps', 'give', 'turn', 'into', 'haven', 'havent', 'don', 'dont', 'isn',
            'really', 'every', 'each', 'when', 'then', 'if', 'than', 'one', 'new', 'good', 'best', 'nice',
            'want', 'wanted', 'trying', 'try', 'going', 'got', 'add', 'set', 'up', 'out', 'up', 'on'];
    }

    /**
     * Per-plugin knowledge.
     *
     * - need: teacher, trainer and admin phrasing for what the plugin is for.
     * - pitch: a short plain-English summary, where the catalogue copy is long or technical.
     * - highlights: features to show, where the catalogue's list is only install metadata.
     * - playlist: the exact title of the plugin's playlist on the LMS Hosting Services channel.
     * - audience: who it is mainly for: teacher, admin or both.
     *
     * @return array Component => knowledge.
     */
    public static function plugins(): array {
        return [
            'quiz_aigrader' => [
                'need' => 'grade essays mark essays essay marking automatic essay feedback ai marking written answers ' .
                    'short answer long answer extended response quiz essay questions rubric marking criteria grade faster ' .
                    'save marking time consistent marks feedback to students approve grades gradebook',
                'playlist' => 'AI Essay Grader - Moodle Quiz Report Plugin',
                'audience' => 'teacher',
            ],
            'block_aigrader_dashboard' => [
                'need' => 'see ungraded essays waiting to be marked marking queue backlog email when essays need grading ' .
                    'dashboard of essays to grade overdue marking',
                'playlist' => 'AI Essay Grader - Moodle Quiz Report Plugin',
                'audience' => 'teacher',
            ],
            'quizaccess_aigrader' => [
                'need' => 'stop quiz reattempts until essays are graded only redo wrong questions hide correct answers on ' .
                    'reattempt retry only incorrect questions resubmission',
                'playlist' => 'AI Essay Grader - Moodle Quiz Report Plugin',
                'audience' => 'teacher',
            ],
            'local_fastgrade' => [
                'need' => 'mark assignments faster grade assignments annotate on the work dictate feedback voice feedback ' .
                    'marking without ai credits rule based marking marking reports quiz essays',
                'playlist' => 'Fast Grade - Moodle Plugin',
                'highlights' => ['Mark directly on the work', 'Rule-based marking drafts', 'Dictated feedback',
                    'Marking reports', 'Teacher approval before release', 'No AI credits'],
                'audience' => 'teacher',
            ],
            'gradingform_benchmarks' => [
                'need' => 'rubric marking guide performance criteria knowledge evidence performance evidence import unit ' .
                    'from training.gov.au tga assessment criteria grading method benchmarks',
                'audience' => 'teacher',
            ],
            'mod_smartworkbook' => [
                'need' => 'workbook worksheet turn word or pdf workbook into online activity ai marks answers model answers ' .
                    'short answer extended response table questions learner workbook assessment',
                'audience' => 'teacher',
            ],
            'plagiarism_essayguard' => [
                'need' => 'detect cheating plagiarism students copying chatgpt ai written essays paste detection typing ' .
                    'behaviour academic integrity risk badge copy paste',
                'playlist' => 'Essay Guard - Moodle Plugin',
                'audience' => 'teacher',
            ],
            'plagiarism_docguard' => [
                'need' => 'check uploaded word pdf assignments for plagiarism ai detection chatgpt written documents ' .
                    'similarity between students copied submissions integrity report',
                'audience' => 'teacher',
            ],
            'mod_contentcreator' => [
                'need' => 'create lesson content write learning material build topic pages generate course content ai ' .
                    'lessons narration voiceover images learning journey reading content',
                'playlist' => 'AI Content Creator - Moodle Activity',
                'audience' => 'teacher',
            ],
            'local_aicoursebuilder' => [
                'need' => 'build a whole course automatically course from pdf course from word document curriculum to ' .
                    'course plan create many activities at once course outline',
                'playlist' => 'AI Course Builder - Moodle Plugin',
                'pitch' => 'Turn curriculum text, a PDF or a Word file into a reviewed Moodle course plan. You approve ' .
                    'the plan, then it builds the course and its activities for you.',
                'highlights' => ['Curriculum, PDF or DOCX in', 'Course plan you approve first', 'Creates courses and ' .
                    'activities', 'Uses each plugin\'s own generation', 'Free to install'],
                'audience' => 'both',
            ],
            'mod_aiactivities' => [
                'need' => 'interactive revision activities drag and drop matching flashcards true false fill in the blank ' .
                    'sorting games engaging practice from my notes gamify learning',
                'playlist' => 'AI Learning Activities - Moodle Module Activity',
                'audience' => 'teacher',
            ],
            'mod_aiknowledgecheck' => [
                'need' => 'quiz multiple choice questions knowledge check generate quiz questions mcq test understanding ' .
                    'check learning gradebook quiz voiceover explanations',
                'playlist' => 'AI Knowledge Check - Moodle Activity Module',
                'audience' => 'teacher',
            ],
            'local_aiquizmaker' => [
                'need' => 'write quiz questions generate question bank essay questions moodle xml export competency mapped ' .
                    'questions model answers rubric question bank',
                'audience' => 'teacher',
            ],
            'local_aiquizremedial' => [
                'need' => 'help students who got quiz questions wrong remedial revision explain wrong answers follow up ' .
                    'practice struggling students intervention reteach',
                'playlist' => 'AI Quiz Remedial Learning - Moodle Plugin',
                'audience' => 'teacher',
            ],
            'mod_aibranchedscenario' => [
                'need' => 'branching scenario decision making roleplay realistic workplace scenario choose your path ' .
                    'consequences policy procedure training soft skills judgement',
                'audience' => 'teacher',
            ],
            'mod_aisoftskills' => [
                'need' => 'soft skills communication customer service conflict workplace behaviour roleplay scenes ' .
                    'interpersonal skills branching lessons',
                'playlist' => 'AI Soft Skills - Moodle Activity',
                'audience' => 'teacher',
            ],
            'mod_productexplainer' => [
                'need' => 'slides presentation from pdf product training explain a concept slide show retail staff ' .
                    'product knowledge',
                'audience' => 'teacher',
            ],
            'mod_slideshow' => [
                'need' => 'upload powerpoint slides narration voiceover slideshow induction site tour video slides ' .
                    'watch time',
                'audience' => 'teacher',
            ],
            'mod_slides' => [
                'need' => 'build slides flip cards matching slides interactive slide lesson presentation without ai',
                'audience' => 'teacher',
            ],
            'mod_aivideoactivity' => [
                'need' => 'youtube video with questions quiz on a video audio listening activity transcript questions ' .
                    'watch and answer',
                'audience' => 'teacher',
            ],
            'mod_aiinteractivevideo' => [
                'need' => 'interactive video youtube questions pop up during video timestamps transcript video lesson ' .
                    'pause video ask questions',
                'playlist' => 'AI Interactive Video - Moodle Plugin',
                'audience' => 'teacher',
            ],
            'mod_visuallearning' => [
                'need' => 'illustrated learning book picture cards visual learners narrated concept cards low literacy ' .
                    'images recap true false',
                'playlist' => 'AI Visual Learning - Moodle Activity',
                'highlights' => ['Illustrated learning books', 'Narrated concept cards', 'Periodic recaps',
                    'True/false checks'],
                'audience' => 'teacher',
            ],
            'mod_aisafetyhazards' => [
                'need' => 'workplace safety hazard spotting whs ohs identify hazards ppe controls risk control induction ' .
                    'safety training',
                'playlist' => 'AI Safety Hazard - Moodle Activity',
                'highlights' => ['Interactive hazard scenes', 'Spot three hazards', 'Distractor items',
                    'Elimination, risk-reduction and PPE controls'],
                'audience' => 'teacher',
            ],
            'mod_aianatomy' => [
                'need' => 'anatomy 3d body nursing health science medical students muscles bones organs',
                'playlist' => 'AI Anatomy - Moodle Activity',
                'audience' => 'teacher',
            ],
            'mod_aiscience' => [
                'need' => 'science experiment virtual lab students measure and report lab notebook physics chemistry ' .
                    'biology simulation',
                'playlist' => 'AI Science Experiment - Moodle Activity',
                'highlights' => ['Experiments from your brief', 'Four ready-made experiments', 'Lab notebook',
                    'Class insights', 'Teacher-approved AI marking'],
                'audience' => 'teacher',
            ],
            'mod_labeldiagram' => [
                'need' => 'label a diagram drag labels onto image parts of a diagram identify parts test diagram labelling',
                'playlist' => 'Label Diagram - Moodle Activity Module',
                'highlights' => ['Study, Practice and Test modes', 'Drag, tap or keyboard placement',
                    'Server-marked tests', 'Teacher reports'],
                'audience' => 'teacher',
            ],
            'mod_ailanguageteacher' => [
                'need' => 'teach a language esl english lessons phrases pronunciation speaking practice language learning',
                'playlist' => 'AI Language Teacher - Moodle Plugin',
                'audience' => 'teacher',
            ],
            'mod_aispeakwrite' => [
                'need' => 'language exam writing speaking practice feedback on writing esl exam preparation speaking test',
                'playlist' => 'AI Language Writing & Speaking - Moodle Plugin',
                'highlights' => ['Exam writing and speaking practice', 'School-specific tips', 'Thinking hints',
                    'Explanatory AI feedback'],
                'audience' => 'teacher',
            ],
            'mod_vocabmastery' => [
                'need' => 'vocabulary words spelling flashcards word lists language vocabulary practice import word list',
                'playlist' => 'Vocabulary Mastery - Moodle Activity',
                'audience' => 'teacher',
            ],
            'mod_aivirtualclassroom' => [
                'need' => 'live online class virtual classroom zoom alternative webinar teach live breakout rooms ' .
                    'captions class summary video conference',
                'playlist' => 'AI Virtual Classroom - Moodle Activity',
                'highlights' => ['Live classes inside Moodle', 'Translated captions', 'Class summaries',
                    'Breakout rooms', 'Marked student boards'],
                'audience' => 'both',
            ],
            'mod_evidencechecklist' => [
                'need' => 'observation checklist practical assessment assessor sign off evidence workplace observation ' .
                    'third party report',
                'playlist' => 'Evidence Checklist - Moodle Activity',
                'highlights' => ['Practical observation checklists', 'Mapped evidence', 'Assessor signatures',
                    'Reporting', 'No AI charges'],
                'audience' => 'teacher',
            ],
            'assignsubmission_videoevidence' => [
                'need' => 'students submit video evidence assess video assignments recorded practical evidence storage ' .
                    'audit',
                'highlights' => ['Tracked video viewing', 'Retains audio and stills', 'Audit manifest',
                    'Saves storage after grading'],
                'audience' => 'teacher',
            ],
            'mod_verifyid' => [
                'need' => 'verify student identity check id photo webcam id document proof of identity',
                'audience' => 'both',
            ],
            'mod_courseinfo' => [
                'need' => 'course information learner guide course outline rto learner acknowledgement assessment summary',
                'audience' => 'teacher',
            ],
            'mod_learningmapping' => [
                'need' => 'mapping document map assessments to unit requirements gap analysis rto audit mapping ' .
                    'performance criteria',
                'audience' => 'both',
            ],
            'local_rtocompliance' => [
                'need' => 'rto compliance asqa trainer credentials qualifications certificates avetmiss student records ' .
                    'audit ready',
                'playlist' => 'RTO Compliance - Moodle Plugin',
                'pitch' => 'A complete compliance system for Australian RTOs, built around the ASQA 2025 Practice ' .
                    'Guide: trainer credentials, qualifications, student results, certificates and AVETMISS reporting.',
                'audience' => 'admin',
            ],
            'block_rtocompliance' => [
                'need' => 'rto compliance dashboard quick links trainers student records certificates',
                'playlist' => 'RTO Compliance - Moodle Plugin',
                'audience' => 'admin',
            ],
            'local_rplkit' => [
                'need' => 'rpl recognition of prior learning rpl kit evidence unit of competency',
                'audience' => 'both',
            ],
            'local_beacon' => [
                'need' => 'reports analytics learner progress completion reports quiz performance attendance grades ' .
                    'enrolment scheduled email reports export csv pdf',
                'playlist' => 'Beacon Reports - Moodle Plugin',
                'audience' => 'both',
            ],
            'block_studentactivity' => [
                'need' => 'participation evidence time on task when students started finished activity log ' .
                    'engagement evidence',
                'audience' => 'teacher',
            ],
            'block_my_students_progress' => [
                'need' => 'see my students progress who is behind struggling completion rates track students at risk',
                'audience' => 'teacher',
            ],
            'block_my_progress' => [
                'need' => 'students see their own progress motivation progress bars',
                'audience' => 'both',
            ],
            'local_student_export' => [
                'need' => 'export student evidence download student work audit evidence pack',
                'audience' => 'admin',
            ],
            'local_emailstudents' => [
                'need' => 'email students remind students about missing assignments not submitted message learners bulk email ' .
                    'templates chase students',
                'playlist' => 'Email Students - Moodle Plugin',
                'highlights' => ['Personalised emails', 'Filter by course, group or progress', 'Missing-assignment ' .
                    'targeting', 'Templates and attachments', 'Delivery reports'],
                'audience' => 'teacher',
            ],
            'mod_certificatepro' => [
                'need' => 'certificates certificate of completion issue certificates qr code verification',
                'playlist' => 'Certificate Pro - Moodle Plugin',
                'highlights' => ['Certificate designer', 'Multi-page layouts', 'Bulk download of issued certificates',
                    'Branded backgrounds and seals', 'Public QR verification'],
                'audience' => 'both',
            ],
            'local_trainingmatrix' => [
                'need' => 'staff training matrix compliance training expiry department position competency tracking',
                'highlights' => ['Training by department and position', 'Completion and expiry tracking',
                    'Learner progress view'],
                'audience' => 'admin',
            ],
            'local_trainingpathways' => [
                'need' => 'training pathways assign training programs learning paths',
                'highlights' => ['Training programs', 'Assigned pathways', 'Verified learning evidence'],
                'audience' => 'admin',
            ],
            'block_trainingmatrix' => [
                'need' => 'learner training progress block',
                'audience' => 'admin',
            ],
            'block_trainingmatrix_teacher' => [
                'need' => 'manager staff training dashboard',
                'audience' => 'admin',
            ],
            'block_trainingpathways' => [
                'need' => 'learner pathway block',
                'audience' => 'admin',
            ],
            'local_workshops' => [
                'need' => 'workshop booking face to face sessions webinar scheduling venue attendance',
                'audience' => 'admin',
            ],
            'availability_workshopattendance' => [
                'need' => 'restrict activity by workshop attendance',
                'highlights' => ['Restrict by workshop attendance', 'Works with Workshop Scheduler'],
                'audience' => 'teacher',
            ],
            'local_recertify' => [
                'need' => 'recertification annual refresher training reset completion every year expiry',
                'audience' => 'admin',
            ],
            'local_groupmanager' => [
                'need' => 'rolling intakes cohort start end dates time limited access groups',
                'audience' => 'admin',
            ],
            'enrol_prerequisite' => [
                'need' => 'prerequisite courses complete one course before another lln gate',
                'audience' => 'admin',
            ],
            'local_courseavailabilitydelay' => [
                'need' => 'delay course appearing drip release show course after days',
                'audience' => 'admin',
            ],
            'local_groupcap' => [
                'need' => 'limit group size maximum group members',
                'audience' => 'admin',
            ],
            'local_completionsuspend' => [
                'need' => 'suspend students when they complete a course',
                'audience' => 'admin',
            ],
            'local_activitynav' => [
                'need' => 'next previous buttons activity navigation arrows',
                'audience' => 'admin',
            ],
            'format_aicourse' => [
                'need' => 'nicer course layout course format cards banners ai tutor course page looks better',
                'playlist' => 'AI Course Format - Moodle Plugin',
                'audience' => 'both',
            ],
            'theme_lmslabs' => [
                'need' => 'new theme modern look easier navigation site design',
                'playlist' => 'Premium SaaS Theme - Moodle Plugin',
                'highlights' => ['Task-based navigation', 'Ctrl+K / Cmd+K search', 'One-click accent colour',
                    'Student view', 'Streamlined admin screens', 'Free'],
                'audience' => 'admin',
            ],
            'local_cohortbranding' => [
                'need' => 'different branding per client cohort logos colours multi tenant',
                'audience' => 'admin',
            ],
            'local_ailogin' => [
                'need' => 'login page design custom login page branding',
                'audience' => 'admin',
            ],
            'local_sitefont' => [
                'need' => 'change site font typography',
                'audience' => 'admin',
            ],
            'local_lmshomepage' => [
                'need' => 'custom front page home page portal dashboard',
                'audience' => 'admin',
            ],
            'local_custompage' => [
                'need' => 'custom pages html pages add a page to navigation',
                'playlist' => 'Custom Pages - Moodle Plugin',
                'audience' => 'admin',
            ],
            'local_clientportal' => [
                'need' => 'corporate clients employers see their staff progress client portal managers reports',
                'playlist' => 'Client Portal - Moodle Plugin',
                'highlights' => ['Branded portal per client', 'Managers see only their people', 'Progress, grades ' .
                    'and certificates', 'Fewer "how are they going?" emails'],
                'audience' => 'admin',
            ],
            'local_paymentunlockassign' => [
                'need' => 'charge students for resubmission late assignment fees stripe',
                'audience' => 'admin',
            ],
            'paygw_paddle' => [
                'need' => 'sell courses online payment gateway tax gst vat checkout',
                'audience' => 'admin',
            ],
            'auth_simple2fa' => [
                'need' => 'two factor authentication secure admin accounts 2fa authenticator',
                'audience' => 'admin',
            ],
            'local_courseversion' => [
                'need' => 'version control course changes audit trail lock course',
                'audience' => 'admin',
            ],
            'local_mediaoptimiser' => [
                'need' => 'storage full large files reduce storage duplicate files',
                'playlist' => 'Media Optimiser - Moodle Plugin',
                'audience' => 'admin',
            ],
            'local_videocompress' => [
                'need' => 'compress videos large videos storage',
                'audience' => 'admin',
            ],
            'local_scormcompress' => [
                'need' => 'compress scorm packages',
                'audience' => 'admin',
            ],
            'local_chirpvoice' => [
                'need' => 'read scorm aloud rise 360 narration voiceover accessibility',
                'audience' => 'teacher',
            ],
            'tool_speedup' => [
                'need' => 'site is slow speed up moodle site performance page load times slow pages database performance',
                'playlist' => 'Speed Up - Moodle Tool',
                'highlights' => ['Page timing review', 'Database, sessions and tasks checks', 'Security review',
                    'Step-by-step fixes', 'Free'],
                'audience' => 'admin',
            ],
            'local_downalert' => [
                'need' => 'site down alerts monitoring uptime',
                'audience' => 'admin',
            ],
            'local_moodlesupport' => [
                'need' => 'help using moodle how do i questions support',
                'audience' => 'both',
            ],
            'local_studentemail' => [
                'need' => 'student email accounts college email cpanel',
                'audience' => 'admin',
            ],
            'auth_studentemail' => [
                'need' => 'log in with college email',
                'audience' => 'admin',
            ],
            'local_campion' => [
                'need' => 'campion textbooks single sign on',
                'audience' => 'admin',
            ],
            'block_aiplugin_nav' => [
                'need' => 'quick links dashboard settings reports',
                'playlist' => 'Quick Links - Moodle Plugin',
                'audience' => 'admin',
            ],
            'local_aiconfig' => [
                'need' => 'api key site id setup',
                'audience' => 'admin',
            ],
        ];
    }

    /**
     * Quick-start categories shown as tags in the finder.
     *
     * @return array[] Each: key, catalogue categories, a seed request.
     */
    public static function topics(): array {
        return [
            ['key' => 'grading', 'q' => 'mark and grade student work, give feedback'],
            ['key' => 'content', 'q' => 'create lessons and learning content'],
            ['key' => 'quizzes', 'q' => 'quizzes, questions and revision activities'],
            ['key' => 'video', 'q' => 'video, slides and narration'],
            ['key' => 'integrity', 'q' => 'detect cheating, plagiarism and AI-written work'],
            ['key' => 'progress', 'q' => 'track student progress and reports'],
            ['key' => 'rto', 'q' => 'RTO compliance, mapping and evidence'],
            ['key' => 'language', 'q' => 'language learning, speaking and vocabulary'],
            ['key' => 'staff', 'q' => 'staff training, certificates and recertification'],
            ['key' => 'admin', 'q' => 'site admin: storage, speed, security and branding'],
        ];
    }
}
