(function () {
    function initSarahChatbot() {
        const root = document.querySelector('[data-sarah-chatbot]');
        if (!root || root.dataset.sarahReady === '1') {
            return;
        }

        root.dataset.sarahReady = '1';

        const windowEl = root.querySelector('.sarah-chatbot-window');
        const launcher = root.querySelector('.sarah-chatbot-launcher');
        const closeBtn = root.querySelector('.sarah-chatbot-close');
        const resetBtn = root.querySelector('.sarah-chatbot-reset');
        const messagesEl = root.querySelector('.sarah-chatbot-messages');
        const actionsEl = root.querySelector('.sarah-chatbot-actions');
        const form = root.querySelector('.sarah-chatbot-form');
        const input = root.querySelector('.sarah-chatbot-input');
        const hasPricingSection = Boolean(document.querySelector('#pricing'));
        const hasSecuritySection = Boolean(document.querySelector('#security'));
        const hasHowItWorksSection = Boolean(document.querySelector('#how-it-works'));
        const hasApplyForm = Boolean(document.getElementById('demo-form'));

        const welcomeMessage = "Hi, I'm Sarah. I can help with pricing, security, migration, onboarding, training, and support. What would you like to know first?";
        const fallbackMessage = 'I can help with pricing, security, migration, onboarding, training, internet reliability, or support. Which one would you like to explore?';

        const mainMenuSuggestions = [
            'How much does MicroFin cost?',
            'Is our client data secure?',
            'How does migration and go-live work?',
            'Check my application status'
        ];

        const branchSuggestions = {
            pricing: [
                'Is this affordable for a small MFI?',
                'What plans are available?',
                'How do we get started?'
            ],
            security: [
                'Is our client data really secure?',
                'How is data protected?',
                'Are backups included?'
            ],
            migration: [
                'Can we migrate without downtime?',
                'How long before we go live?',
                'What is the onboarding process?'
            ],
            training: [
                'Will our staff need heavy training?',
                'What support do we get after launch?',
                'What if internet is unstable?'
            ]
        };

        const menuTopicByLabel = {
            'how much does microfin cost?': 'pricing',
            'is our client data secure?': 'security',
            'how does migration and go-live work?': 'migration',
            'what training and support do we get?': 'training'
        };

        const languageDictionary = {
            questionWords: [
                'what', 'how', 'why', 'when', 'where', 'who', 'which',
                'is', 'are', 'can', 'do', 'does', 'did', 'will', 'would', 'could', 'should'
            ],
            intents: {
                greeting: ['hi', 'hello', 'hey', 'yo', 'hiya', 'good morning', 'good afternoon', 'good evening'],
                thanks: ['thanks', 'thank you', 'thank you so much', 'salamat', 'ty'],
                farewell: ['bye', 'goodbye', 'see you', 'see you later', 'talk later', 'cya'],
                affirmative: ['yes', 'yeah', 'yep', 'sure', 'okay', 'ok', 'go ahead', 'please do'],
                negative: ['no', 'nope', 'not now', 'maybe later', 'nah'],
                smallTalk: ['how are you', 'how are you doing', 'how is it going', "how's it going"],
                abusive: ['fuck', 'fucking', 'fuck you', 'fuckyou', 'shit', 'bitch', 'asshole', 'stupid', 'idiot', 'moron', 'tanga']
            },
            topicKeywords: {
                agent: ['agent', 'human', 'person', 'staff', 'representative'],
                pricing: ['pricing', 'budget', 'afford', 'affordable', 'price', 'cost', 'plan', 'plans', 'subscription', 'small mfi', 'small institution'],
                security: ['security', 'secure', 'data protected', 'encryption', 'encrypt', 'privacy', 'breach', 'backup', 'backups', 'compliance', 'audit'],
                migration: ['migration', 'migrate', 'go live', 'go-live', 'timeline', 'downtime', 'onboarding', 'implementation', 'cutover', 'validation'],
                training: ['training', 'support', 'internet', 'unstable', 'connectivity', 'network', 'adoption', 'staff readiness'],
                status: ['status', 'check status', 'application status', 'reference id', 'reference id', 'track', 'tracking']
            },
            presetAnswers: {
                greeting: 'Hi too. Nice to meet you. I can help with pricing, security, migration, onboarding, training, and support.',
                smallTalk: 'I am doing well, thank you. I am ready to help with anything about MicroFin.',
                thanksMain: 'You are welcome. What would you like to explore next?',
                thanksTopic: 'You are welcome. Would you like to continue with this topic?',
                farewell: 'Thanks for chatting. I am here anytime you need help.',
                yesMain: 'Great. Choose any question below and I will guide you.',
                abusive: 'Let us keep this respectful. I can still help you with pricing, security, migration, onboarding, training, internet reliability, or support.',
                wordFallback: 'I got your word. Could you share a little more detail or choose one topic below?',
                questionFallback: 'Great question. I can guide you on pricing, security, migration, onboarding, training, internet reliability, or support.',
                statementFallback: 'Thanks for sharing that. I can help further on pricing, security, migration, onboarding, training, internet reliability, or support.'
            }
        };

        const followUpTopicByLabel = buildFollowUpTopicLookup();

        let initializedConversation = false;
        let currentTopic = 'main';
        let pendingResponseTimer = null;
        let responseToken = 0;

        function openChat() {
            root.classList.add('is-open');
            windowEl.hidden = false;
            launcher.setAttribute('aria-expanded', 'true');

            if (!initializedConversation) {
                appendBotMessage(welcomeMessage);
                renderSuggestions(mainMenuSuggestions);
                currentTopic = 'main';
                initializedConversation = true;
            }

            window.setTimeout(function () {
                input.focus();
                scrollMessagesToBottom();
            }, 60);
        }

        function closeChat() {
            root.classList.remove('is-open');
            launcher.setAttribute('aria-expanded', 'false');
            windowEl.hidden = true;
        }

        function scrollMessagesToBottom() {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function appendMessage(text, sender, allowHtml) {
            const bubble = document.createElement('div');
            bubble.className = 'sarah-chatbot-message sarah-chatbot-message--' + sender;

            if (allowHtml) {
                bubble.innerHTML = text;
            } else {
                bubble.textContent = text;
            }

            messagesEl.appendChild(bubble);
            scrollMessagesToBottom();
        }

        function appendUserMessage(text) {
            appendMessage(text, 'user', false);
        }

        function appendBotMessage(text) {
            appendMessage(text, 'bot', true);
        }

        function appendTypingIndicator() {
            const bubble = document.createElement('div');
            bubble.className = 'sarah-chatbot-message sarah-chatbot-message--bot sarah-chatbot-message--typing';
            bubble.setAttribute('aria-hidden', 'true');
            bubble.innerHTML = '<span></span><span></span><span></span>';
            messagesEl.appendChild(bubble);
            scrollMessagesToBottom();
            return bubble;
        }

        function removeTypingIndicator(indicator) {
            if (indicator && indicator.parentNode === messagesEl) {
                messagesEl.removeChild(indicator);
            }
        }

        function clearTypingIndicators() {
            messagesEl.querySelectorAll('.sarah-chatbot-message--typing').forEach(function (typingBubble) {
                typingBubble.remove();
            });
        }

        function resetConversation() {
            responseToken += 1;

            if (pendingResponseTimer !== null) {
                window.clearTimeout(pendingResponseTimer);
                pendingResponseTimer = null;
            }

            clearTypingIndicators();
            messagesEl.innerHTML = '';
            appendBotMessage(welcomeMessage);
            renderSuggestions(mainMenuSuggestions);
            currentTopic = 'main';
            initializedConversation = true;
            input.value = '';

            window.setTimeout(function () {
                input.focus();
                scrollMessagesToBottom();
            }, 0);
        }

        function linkHtml(label, href) {
            return '<a class="sarah-chatbot-inline-link" href="' + href + '">' + label + '</a>';
        }

        function normalizeText(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .replace(/\s+/g, ' ');
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderSuggestions(suggestions) {
            const nextSuggestions = Array.isArray(suggestions) ? suggestions.filter(Boolean) : [];

            if (!nextSuggestions.length) {
                actionsEl.innerHTML = '';
                return;
            }

            actionsEl.innerHTML = nextSuggestions.map(function (label) {
                const safeLabel = escapeHtml(label);
                return '<button type="button" class="sarah-chatbot-chip" data-prompt="' + safeLabel + '">' + safeLabel + '</button>';
            }).join('');
        }

        function buildFollowUpTopicLookup() {
            const lookup = {};

            Object.keys(branchSuggestions).forEach(function (topic) {
                branchSuggestions[topic].forEach(function (label) {
                    const normalizedLabel = normalizeText(label);
                    const searchableLabel = normalizeText(normalizedLabel.replace(/[^a-z0-9\s'-]/g, ' '));
                    lookup[normalizedLabel] = topic;
                    lookup[searchableLabel] = topic;
                });
            });

            return lookup;
        }

        function getBranchSuggestions(topic, excludeLabel) {
            const allSuggestions = branchSuggestions[topic] || [];

            if (!excludeLabel) {
                return allSuggestions.slice(0, 3);
            }

            const excluded = normalizeText(excludeLabel);
            const filtered = allSuggestions.filter(function (label) {
                return normalizeText(label) !== excluded;
            });

            return filtered.slice(0, 3);
        }

        function buildAgentResponse() {
            return {
                answer: 'Our human staff is ready to help! Please fill out the ' + linkHtml('Talk to an Agent form', 'demo.php?expert=1') + ' with your institution\'s details, and an expert will reach out to you via email shortly.',
                topic: 'agent',
                suggestions: []
            };
        }

        function buildMainMenuResponse(answerText) {
            return {
                answer: answerText,
                topic: 'main',
                suggestions: mainMenuSuggestions
            };
        }

        function tokenizeInput(searchableText) {
            if (!searchableText) {
                return [];
            }

            return searchableText
                .split(/\s+/)
                .filter(Boolean);
        }

        function analyzeInput(rawInput) {
            const raw = String(rawInput || '').trim();
            const normalized = normalizeText(raw);
            const searchable = normalizeText(normalized.replace(/[^a-z0-9\s'-]/g, ' '));
            const tokens = tokenizeInput(searchable);
            const hasQuestionMark = /\?\s*$/.test(raw);
            const startsWithQuestionWord = languageDictionary.questionWords.some(function (word) {
                return searchable === word || searchable.indexOf(word + ' ') === 0;
            });

            let inputKind = 'statement';

            if (tokens.length <= 1) {
                inputKind = 'word';
            } else if (hasQuestionMark || startsWithQuestionWord) {
                inputKind = 'question';
            }

            return {
                normalized: normalized,
                searchable: searchable,
                tokens: tokens,
                inputKind: inputKind
            };
        }

        function hasPhraseMatch(searchableText, tokens, phraseList) {
            return phraseList.some(function (phrase) {
                const cleanPhrase = normalizeText(phrase);
                if (!cleanPhrase) {
                    return false;
                }

                if (cleanPhrase.indexOf(' ') !== -1) {
                    return searchableText === cleanPhrase
                        || searchableText.indexOf(cleanPhrase + ' ') === 0
                        || searchableText.endsWith(' ' + cleanPhrase)
                        || searchableText.indexOf(' ' + cleanPhrase + ' ') !== -1;
                }

                return tokens.indexOf(cleanPhrase) !== -1;
            });
        }

        function containsAbusiveLanguage(inputAnalysis) {
            const searchableText = inputAnalysis.searchable;
            const compactText = searchableText.replace(/\s+/g, '');

            if (hasPhraseMatch(searchableText, inputAnalysis.tokens, languageDictionary.intents.abusive)) {
                return true;
            }

            return /(f\W*u\W*c\W*k\W*y\W*o\W*u|fuckyou|stfu)/i.test(compactText);
        }

        function detectDictionaryIntent(inputAnalysis) {
            const searchableText = inputAnalysis.searchable;
            const tokens = inputAnalysis.tokens;
            const isShortUtterance = tokens.length <= 4;
            const hasTopicHint = Boolean(detectTopicFromText(inputAnalysis));

            if (containsAbusiveLanguage(inputAnalysis)) {
                return 'abusive';
            }

            if (!hasTopicHint && tokens.length <= 8 && hasPhraseMatch(searchableText, tokens, languageDictionary.intents.smallTalk)) {
                return 'smallTalk';
            }

            if (!hasTopicHint && isShortUtterance && hasPhraseMatch(searchableText, tokens, languageDictionary.intents.greeting)) {
                return 'greeting';
            }

            if (!hasTopicHint && isShortUtterance && hasPhraseMatch(searchableText, tokens, languageDictionary.intents.thanks)) {
                return 'thanks';
            }

            if (!hasTopicHint && isShortUtterance && hasPhraseMatch(searchableText, tokens, languageDictionary.intents.farewell)) {
                return 'farewell';
            }

            if (!hasTopicHint && isShortUtterance && hasPhraseMatch(searchableText, tokens, languageDictionary.intents.affirmative)) {
                return 'affirmative';
            }

            if (!hasTopicHint && isShortUtterance && hasPhraseMatch(searchableText, tokens, languageDictionary.intents.negative)) {
                return 'negative';
            }

            return null;
        }

        function detectTopicFromText(inputAnalysis) {
            const searchableText = inputAnalysis.searchable;
            const tokens = inputAnalysis.tokens;

            if (hasPhraseMatch(searchableText, tokens, languageDictionary.topicKeywords.agent)) {
                return 'agent';
            }

            if (hasPhraseMatch(searchableText, tokens, languageDictionary.topicKeywords.pricing)) {
                return 'pricing';
            }

            if (hasPhraseMatch(searchableText, tokens, languageDictionary.topicKeywords.security)) {
                return 'security';
            }

            if (hasPhraseMatch(searchableText, tokens, languageDictionary.topicKeywords.migration)) {
                return 'migration';
            }

            if (hasPhraseMatch(searchableText, tokens, languageDictionary.topicKeywords.training)) {
                return 'training';
            }

            if (hasPhraseMatch(searchableText, tokens, languageDictionary.topicKeywords.status)) {
                return 'status';
            }

            return null;
        }

        function buildBranchIntroResponse(topic) {
            if (topic === 'pricing') {
                return {
                    answer: hasPricingSection
                        ? 'Great question. We can cover budget fit, available plans, and first steps. You can also review ' + linkHtml('Pricing', '#pricing') + '.'
                        : 'Great question. We can cover budget fit, available plans, and first steps.',
                    topic: 'pricing',
                    suggestions: getBranchSuggestions('pricing')
                };
            }

            if (topic === 'security') {
                return {
                    answer: hasSecuritySection
                        ? 'Happy to help. We can go through data safety, protection methods, and backups. See ' + linkHtml('Security', '#security') + ' for details.'
                        : 'Happy to help. We can go through data safety, protection methods, and backups.',
                    topic: 'security',
                    suggestions: getBranchSuggestions('security')
                };
            }

            if (topic === 'migration') {
                return {
                    answer: hasHowItWorksSection
                        ? 'Sure. We can walk through downtime risk, go-live timeline, and onboarding flow. You can check ' + linkHtml('How it Works', '#how-it-works') + ' too.'
                        : 'Sure. We can walk through downtime risk, go-live timeline, and onboarding flow.',
                    topic: 'migration',
                    suggestions: getBranchSuggestions('migration')
                };
            }

            if (topic === 'training') {
                return {
                    answer: 'Absolutely. We can cover training effort, post-launch support, and internet reliability.',
                    topic: 'training',
                    suggestions: getBranchSuggestions('training')
                };
            }

            if (topic === 'status') {
                return {
                    answer: 'I can help with that. Please enter your **Reference ID** (e.g., ABC123DEF4) from your confirmation email, and I will check your application status for you.',
                    topic: 'status',
                    suggestions: ['How do I get a Reference ID?']
                };
            }

            return {
                answer: fallbackMessage,
                topic: 'main',
                suggestions: mainMenuSuggestions
            };
        }

        function buildPricingResponse(text, allowIntro) {
            if (/(afford|affordable|budget|small mfi|small institution|small team)/i.test(text)) {
                return {
                    answer: hasPricingSection
                        ? 'Many small MFIs start with a lean plan and scale later. You can review options in ' + linkHtml('Pricing', '#pricing') + '.'
                        : 'Many small MFIs start with a lean plan and scale later as operations grow.',
                    topic: 'pricing',
                    suggestions: getBranchSuggestions('pricing', 'Is this affordable for a small MFI?')
                };
            }

            if (/(plans|plan|available|starter|pro|enterprise|unlimited|pricing|price|cost)/i.test(text)) {
                return {
                    answer: hasPricingSection
                        ? 'MicroFin offers Starter, Pro, Enterprise, and Unlimited. See the breakdown in ' + linkHtml('Pricing', '#pricing') + '.'
                        : 'MicroFin offers Starter, Pro, Enterprise, and Unlimited plans based on scale.',
                    topic: 'pricing',
                    suggestions: getBranchSuggestions('pricing', 'What plans are available?')
                };
            }

            if (/(get started|start|next step|apply|onboard|onboarding)/i.test(text)) {
                return {
                    answer: hasApplyForm
                        ? 'You can start right here by completing the form, verifying with OTP, then submitting your request.'
                        : 'You can begin from ' + linkHtml('Apply Now', 'demo.php') + ' and the team will guide you through onboarding.',
                    topic: 'pricing',
                    suggestions: getBranchSuggestions('pricing', 'How do we get started?')
                };
            }

            if (allowIntro !== false) {
                return buildBranchIntroResponse('pricing');
            }

            return null;
        }

        function buildSecurityResponse(text, allowIntro) {
            if (/(client data|data secure|really secure|secure)/i.test(text)) {
                return {
                    answer: hasSecuritySection
                        ? 'Yes, security is a core focus with tenant isolation, encryption, and backup controls. See ' + linkHtml('Security', '#security') + ' for the overview.'
                        : 'Yes, security is a core focus with tenant isolation, encryption, and backup controls.',
                    topic: 'security',
                    suggestions: getBranchSuggestions('security', 'Is our client data really secure?')
                };
            }

            if (/(data protected|protected|encryption|encrypt|tls|aes|isolation)/i.test(text)) {
                return {
                    answer: 'Data is protected through tenant isolation, encrypted traffic, and secure storage practices.',
                    topic: 'security',
                    suggestions: getBranchSuggestions('security', 'How is data protected?')
                };
            }

            if (/(backup|backups)/i.test(text)) {
                return {
                    answer: 'Yes, automated backups are part of the platform security approach.',
                    topic: 'security',
                    suggestions: getBranchSuggestions('security', 'Are backups included?')
                };
            }

            if (allowIntro !== false) {
                return buildBranchIntroResponse('security');
            }

            return null;
        }

        function buildMigrationResponse(text, allowIntro) {
            if (/(migrate|migration|without downtime|downtime|cutover)/i.test(text)) {
                return {
                    answer: 'Migration is done in stages: mapping, test import, validation, then controlled cutover to reduce downtime risk.',
                    topic: 'migration',
                    suggestions: getBranchSuggestions('migration', 'Can we migrate without downtime?')
                };
            }

            if (/(how long|timeline|before we go live|go live|go-live)/i.test(text)) {
                return {
                    answer: 'Go-live timing depends on readiness, but onboarding is designed to move in days, not months.',
                    topic: 'migration',
                    suggestions: getBranchSuggestions('migration', 'How long before we go live?')
                };
            }

            if (/(onboarding process|onboarding|process|implementation flow)/i.test(text)) {
                return {
                    answer: hasHowItWorksSection
                        ? 'The process is usually discovery, provisioning, then guided onboarding and validation. See ' + linkHtml('How it Works', '#how-it-works') + '.'
                        : 'The process is usually discovery, provisioning, then guided onboarding and validation.',
                    topic: 'migration',
                    suggestions: getBranchSuggestions('migration', 'What is the onboarding process?')
                };
            }

            if (allowIntro !== false) {
                return buildBranchIntroResponse('migration');
            }

            return null;
        }

        function buildTrainingResponse(text, allowIntro) {
            if (/(heavy training|need heavy training|training|learning curve|adoption)/i.test(text)) {
                return {
                    answer: 'Usually not heavy. Teams are onboarded in steps, starting with admins, then branch and field users.',
                    topic: 'training',
                    suggestions: getBranchSuggestions('training', 'Will our staff need heavy training?')
                };
            }

            if (/(support after launch|after launch|post-launch|post launch|support)/i.test(text)) {
                return {
                    answer: 'After launch, support is available for questions, issue handling, and operational guidance.',
                    topic: 'training',
                    suggestions: getBranchSuggestions('training', 'What support do we get after launch?')
                };
            }

            if (/(internet is unstable|internet|unstable|connectivity|network|offline)/i.test(text)) {
                return {
                    answer: 'If internet is unstable, rollout can be planned around branch conditions with practical fallback steps.',
                    topic: 'training',
                    suggestions: getBranchSuggestions('training', 'What if internet is unstable?')
                };
            }

            if (allowIntro !== false) {
                return buildBranchIntroResponse('training');
            }

            return null;
        }

        function buildResponse(userInput) {
            const inputAnalysis = analyzeInput(userInput);
            const normalizedText = inputAnalysis.normalized;
            const searchableText = inputAnalysis.searchable;
            const dictionaryIntent = detectDictionaryIntent(inputAnalysis);

            if (!searchableText) {
                return buildMainMenuResponse(fallbackMessage);
            }

            if (dictionaryIntent === 'greeting') {
                return buildMainMenuResponse(languageDictionary.presetAnswers.greeting);
            }

            if (dictionaryIntent === 'smallTalk') {
                return buildMainMenuResponse(languageDictionary.presetAnswers.smallTalk);
            }

            if (dictionaryIntent === 'thanks') {
                if (currentTopic !== 'main' && currentTopic !== 'agent') {
                    return {
                        answer: languageDictionary.presetAnswers.thanksTopic,
                        topic: currentTopic,
                        suggestions: getBranchSuggestions(currentTopic)
                    };
                }

                return buildMainMenuResponse(languageDictionary.presetAnswers.thanksMain);
            }

            if (dictionaryIntent === 'farewell') {
                return buildMainMenuResponse(languageDictionary.presetAnswers.farewell);
            }

            if (dictionaryIntent === 'affirmative') {
                if (currentTopic !== 'main' && currentTopic !== 'agent') {
                    return buildBranchIntroResponse(currentTopic);
                }

                return buildMainMenuResponse(languageDictionary.presetAnswers.yesMain);
            }

            if (dictionaryIntent === 'negative') {
                return buildMainMenuResponse(fallbackMessage);
            }

            if (dictionaryIntent === 'abusive') {
                return buildMainMenuResponse(languageDictionary.presetAnswers.abusive);
            }

            const menuTopic = menuTopicByLabel[normalizedText] || menuTopicByLabel[searchableText];

            if (menuTopic) {
                if (menuTopic === 'agent') {
                    return buildAgentResponse();
                }

                return buildBranchIntroResponse(menuTopic);
            }

            const followUpTopic = followUpTopicByLabel[normalizedText] || followUpTopicByLabel[searchableText];

            if (followUpTopic) {
                if (followUpTopic === 'pricing') {
                    return buildPricingResponse(searchableText, true);
                }

                if (followUpTopic === 'security') {
                    return buildSecurityResponse(searchableText, true);
                }

                if (followUpTopic === 'migration') {
                    return buildMigrationResponse(searchableText, true);
                }

                if (followUpTopic === 'training') {
                    return buildTrainingResponse(searchableText, true);
                }
            }

            const detectedTopic = detectTopicFromText(inputAnalysis);

            if (detectedTopic === 'agent') {
                return buildAgentResponse();
            }

            if (detectedTopic === 'pricing') {
                return buildPricingResponse(searchableText, true);
            }

            if (detectedTopic === 'security') {
                return buildSecurityResponse(searchableText, true);
            }

            if (detectedTopic === 'migration') {
                return buildMigrationResponse(searchableText, true);
            }

            if (detectedTopic === 'status') {
                return buildBranchIntroResponse('status');
            }

            if (currentTopic === 'status') {
                if (/^[A-Z0-9]{10}$/i.test(searchableText)) {
                    // This will be handled in submitPrompt with a fetch
                    return { answer: 'Checking status...', topic: 'status', suggestions: [] };
                }
                return {
                    answer: 'Please provide a valid 10-character Reference ID to check your status.',
                    topic: 'status',
                    suggestions: ['How do I get a Reference ID?']
                };
            }

            if (currentTopic === 'pricing') {
                const pricingContextResponse = buildPricingResponse(searchableText, false);
                if (pricingContextResponse) {
                    return pricingContextResponse;
                }
            }

            if (currentTopic === 'security') {
                const securityContextResponse = buildSecurityResponse(searchableText, false);
                if (securityContextResponse) {
                    return securityContextResponse;
                }
            }

            if (currentTopic === 'migration') {
                const migrationContextResponse = buildMigrationResponse(searchableText, false);
                if (migrationContextResponse) {
                    return migrationContextResponse;
                }
            }

            if (currentTopic === 'training') {
                const trainingContextResponse = buildTrainingResponse(searchableText, false);
                if (trainingContextResponse) {
                    return trainingContextResponse;
                }
            }

            if (inputAnalysis.inputKind === 'word') {
                return buildMainMenuResponse(languageDictionary.presetAnswers.wordFallback);
            }

            if (inputAnalysis.inputKind === 'question') {
                return buildMainMenuResponse(languageDictionary.presetAnswers.questionFallback);
            }

            return buildMainMenuResponse(languageDictionary.presetAnswers.statementFallback);
        }

        function submitPrompt(promptText) {
            const cleaned = promptText.trim();
            if (!cleaned) {
                return;
            }

            if (pendingResponseTimer !== null) {
                window.clearTimeout(pendingResponseTimer);
                pendingResponseTimer = null;
                responseToken += 1;
                clearTypingIndicators();
            }

            appendUserMessage(cleaned);
            const typingIndicator = appendTypingIndicator();
            const responseDelay = Math.min(1200, Math.max(320, cleaned.length * 18));
            const requestToken = ++responseToken;

            pendingResponseTimer = window.setTimeout(function () {
                if (requestToken !== responseToken) {
                    removeTypingIndicator(typingIndicator);
                    pendingResponseTimer = null;
                    return;
                }

                if (/^[A-Z0-9]{10}$/i.test(cleaned)) {
                    fetch('api/api_demo.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=check_status&tenant_id=' + encodeURIComponent(cleaned)
                    })
                    .then(response => response.json())
                    .then(data => {
                        removeTypingIndicator(typingIndicator);
                        if (data.success) {
                            appendBotMessage('**Status for ' + data.tenant_name + ':**\n\n' +
                                'Current Status: **' + data.status + '**\n\n' +
                                'Our team is processing your application. We will contact you soon!');
                        } else {
                            appendBotMessage(data.message || 'I couldn\'t find that Reference ID.');
                        }
                        currentTopic = 'main';
                        renderSuggestions(mainMenuSuggestions);
                    })
                    .catch(() => {
                        removeTypingIndicator(typingIndicator);
                        appendBotMessage('Sorry, I encountered an error while checking your status. Please try again later.');
                        renderSuggestions(mainMenuSuggestions);
                    });
                    return;
                }

                const response = buildResponse(cleaned);
                removeTypingIndicator(typingIndicator);
                appendBotMessage(response.answer);
                currentTopic = response.topic || 'main';
                renderSuggestions(response.suggestions);
                pendingResponseTimer = null;
            }, responseDelay);
        }

        launcher.addEventListener('click', function () {
            if (root.classList.contains('is-open')) {
                closeChat();
            } else {
                openChat();
            }
        });

        closeBtn.addEventListener('click', function () {
            closeChat();
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                resetConversation();
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const promptText = input.value;
            input.value = '';
            openChat();
            submitPrompt(promptText);
        });

        actionsEl.addEventListener('click', function (event) {
            const chip = event.target.closest('.sarah-chatbot-chip');
            if (!chip || !actionsEl.contains(chip)) {
                return;
            }

            openChat();
            submitPrompt(chip.dataset.prompt || chip.textContent || '');
        });

        document.querySelectorAll('.js-open-sarah-chat').forEach(function (trigger) {
            trigger.addEventListener('click', function (event) {
                event.preventDefault();
                openChat();
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && root.classList.contains('is-open')) {
                closeChat();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSarahChatbot);
    } else {
        initSarahChatbot();
    }
})();
