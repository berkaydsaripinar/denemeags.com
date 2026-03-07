<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentörlük İhtiyaç Testi - DenemeAGS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8; /* Açık mavi-gri arka plan */
        }
        .card {
            background-color: white;
            border-radius: 1.5rem; /* Daha yuvarlak köşeler */
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease-in-out;
        }
        .btn-primary-theme {
            background-color: #4A69FF; /* Canlı mavi */
            color: white;
            transition: background-color 0.3s ease;
        }
        .btn-primary-theme:hover {
            background-color: #3A58E0; /* Koyu mavi */
        }
        .btn-secondary-theme {
            background-color: #f0f4f8;
            color: #4A69FF;
            border: 2px solid #dde3ff;
            transition: all 0.3s ease;
        }
        .btn-secondary-theme:hover {
            background-color: #dde3ff;
        }
        .answer-option {
            border: 2px solid #e2e8f0;
            transition: all 0.2s ease-in-out;
        }
        .answer-option:hover {
            border-color: #4A69FF;
            background-color: #eff2ff;
            transform: translateY(-2px);
        }
        .answer-option.selected {
            border-color: #4A69FF;
            background-color: #dde3ff;
            box-shadow: 0 0 0 3px rgba(74, 105, 255, 0.3);
        }
        .progress-bar-custom {
            background-color: #4A69FF;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4A69FF;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Markdown formatlaması için temel stiller */
        .prose h3 { font-size: 1.25rem; font-weight: 700; margin-top: 1rem; margin-bottom: 0.5rem; color: #1e293b;}
        .prose ul { list-style-type: disc; margin-left: 1.5rem; }
        .prose li { margin-bottom: 0.25rem; }
        .prose p { margin-bottom: 0.75rem; color: #475569;}
        .mentor-card {
            background: linear-gradient(145deg, #f9fafb, #f0f4f8);
            border: 1px solid #e5e7eb;
        }
        .mentor-badge {
            background-color: #4A69FF;
            color: white;
            box-shadow: 0 2px 4px rgba(74, 105, 255, 0.3);
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div id="quiz-container" class="w-full max-w-2xl mx-auto">

        <!-- Başlangıç Ekranı -->
        <div id="start-screen" class="card p-8 md:p-12 text-center">
            <div class="mx-auto bg-blue-100 p-4 rounded-full mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-compass-fill text-blue-600" viewBox="0 0 16 16"><path d="M15.5 8.516a7.5 7.5 0 1 1-9.462-7.24A1 1 0 0 1 7 0h2a1 1 0 0 1 .962 1.276 7.5 7.5 0 0 1 5.538 7.24zM8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zM8 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10z"/><path d="M6.91 7.09a2 2 0 1 1 2.45 2.45l-2.45-2.45z"/></svg>
            </div>
            <h1 class="text-3xl md:text-4xl font-extrabold text-gray-800 mb-4">Mentöre İhtiyacın Var Mı?</h1>
            <p class="text-gray-600 mb-8">Çalışma alışkanlıklarını ve hedeflerini analiz ederek sana en uygun yolu bulalım. Bu kısa test ile bir profesyonel desteğe ihtiyacın olup olmadığını keşfet!</p>
            <button id="start-btn" class="btn-primary-theme font-bold py-3 px-8 rounded-full text-lg shadow-lg hover:shadow-xl transform hover:scale-105">Teste Başla</button>
        </div>

        <!-- Test Ekranı (Gizli) -->
        <div id="quiz-screen" class="hidden">
            <div class="card p-8 md:p-10">
                <div id="question-header" class="mb-6">
                    <p class="text-blue-600 font-semibold" id="question-number"></p>
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800" id="question-text"></h2>
                </div>
                <div id="answer-buttons" class="space-y-4"></div>
            </div>
            <div class="mt-6">
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div id="progress-bar" class="progress-bar-custom h-2.5 rounded-full" style="width: 0%; transition: width 0.5s ease;"></div>
                </div>
            </div>
        </div>

        <!-- Sonuç Ekranı (Gizli) -->
        <div id="result-screen" class="hidden">
            <div class="card p-6 md:p-10 fade-in">
                <div id="result-loading" class="flex flex-col items-center">
                    <div class="loader"></div>
                    <p class="text-gray-600 mt-4">✨ Sonuçların analiz ediliyor, sana özel tavsiyeler hazırlanıyor...</p>
                </div>
                <div id="result-content" class="hidden text-left">
                    <!-- Gemini'den gelen sonuç buraya eklenecek -->
                </div>
                <!-- Sonuç 1: Koça İhtiyaç Var (Gizli) -->
                <div id="result-yes-extra" class="hidden mt-8">
                    <hr class="my-6">
                    <h3 class="text-2xl font-bold text-gray-800 text-center mb-6">Sen de Başarabilirsin!</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Mentor Kartı 1 -->
                        <div class="mentor-card rounded-xl p-4 flex flex-col items-center text-center">
                            <img src="https://placehold.co/100x100/E0E7FF/4A69FF?text=D.O" class="w-24 h-24 rounded-full mb-3 border-4 border-white shadow-md" alt="Dila Okutan Hoca">
                            <h4 class="font-bold text-lg text-gray-800">Dila Okutan Hoca</h4>
                            <div class="mentor-badge text-sm font-bold py-1 px-3 rounded-full mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trophy-fill inline-block mr-1 -mt-1" viewBox="0 0 16 16"><path d="M2.5.5A.5.5 0 0 1 3 .5V1h10V.5a.5.5 0 0 1 .5-.5h0a.5.5 0 0 1 .5.5V1H15a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-.5v2.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 7.5V5H.5A.5.5 0 0 1 0 4.5v-2A.5.5 0 0 1 .5 2H2V.5a.5.5 0 0 1 .5-.5zM3 13.5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5V8H3z"/></svg>
                                Türkiye 110.'su
                            </div>
                        </div>
                        <!-- Mentor Kartı 2 -->
                        <div class="mentor-card rounded-xl p-4 flex flex-col items-center text-center">
                            <img src="https://placehold.co/100x100/E0E7FF/4A69FF?text=D.M" class="w-24 h-24 rounded-full mb-3 border-4 border-white shadow-md" alt="Derya Mihmat Hoca">
                            <h4 class="font-bold text-lg text-gray-800">Derya Mihmat Hoca</h4>
                            <div class="mentor-badge text-sm font-bold py-1 px-3 rounded-full mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trophy-fill inline-block mr-1 -mt-1" viewBox="0 0 16 16"><path d="M2.5.5A.5.5 0 0 1 3 .5V1h10V.5a.5.5 0 0 1 .5-.5h0a.5.5 0 0 1 .5.5V1H15a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-.5v2.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 7.5V5H.5A.5.5 0 0 1 0 4.5v-2A.5.5 0 0 1 .5 2H2V.5a.5.5 0 0 1 .5-.5zM3 13.5A1.5 1.5 0 0 0 4.5 15h7a1.5 1.5 0 0 0 1.5-1.5V8H3z"/></svg>
                                Türkiye 332.'si
                            </div>
                        </div>
                    </div>
                </div>
                <div id="result-actions" class="hidden mt-8 text-center space-y-4">
                    <!-- Eylem butonları buraya eklenecek -->
                </div>
            </div>
        </div>
    </div>

<script>
    // ... (HTML Elementleri, WhatsApp Ayarları, Sorular ve Puanlama aynı kalacak) ...
    const startScreen = document.getElementById('start-screen');
    const quizScreen = document.getElementById('quiz-screen');
    const resultScreen = document.getElementById('result-screen');
    const startBtn = document.getElementById('start-btn');
    const questionNumberText = document.getElementById('question-number');
    const questionText = document.getElementById('question-text');
    const answerButtons = document.getElementById('answer-buttons');
    const progressBar = document.getElementById('progress-bar');
    const resultLoading = document.getElementById('result-loading');
    const resultContent = document.getElementById('result-content');
    const resultYesExtra = document.getElementById('result-yes-extra');
    const resultActions = document.getElementById('result-actions');
    const whatsappNumber = "905061544019"; 
    const whatsappMessage = "Merhaba, mentörlük ihtiyaç testim sonucunda size ulaşıyorum. Süreç hakkında bilgi alabilir miyim?";
    const questions = [ { question: "Hedeflerinizi belirlerken ne kadar net olabiliyorsunuz?", answers: [ { text: "Çok netim, ne istediğimi ve nasıl ulaşacağımı biliyorum.", points: 0 }, { text: "Genel bir fikrim var ama detaylı bir planım yok.", points: 1 }, { text: "Sık sık kararsız kalıyorum ve ne istediğimden emin değilim.", points: 2 } ] }, { question: "Oluşturduğunuz bir çalışma programına ne kadar sadık kalabiliyorsunuz?", answers: [ { text: "Neredeyse her zaman programıma uyarım.", points: 0 }, { text: "Genellikle uyarım ama bazen aksamalar olur.", points: 1 }, { text: "Program yapmakta zorlanıyorum ve genellikle uyamıyorum.", points: 2 } ] }, { question: "Motivasyonunuz düştüğünde tekrar toparlanmanız ne kadar sürer?", answers: [ { text: "Kısa sürede kendimi tekrar motive edebilirim.", points: 0 }, { text: "Birkaç gün veya daha uzun sürebilir.", points: 1 }, { text: "Günlerce sürer, bazen hiç toparlanamam.", points: 2 } ] }, { question: "Deneme sınavı sonuçlarınızı analiz edip eksiklerinizi gidermek için bir stratejiniz var mı?", answers: [ { text: "Evet, her deneme sonrası detaylı analiz yapıp planımı güncelliyorum.", points: 0 }, { text: "Eksik konularımı biliyorum ama nasıl gidereceğimi tam planlayamıyorum.", points: 1 }, { text: "Hayır, genellikle sadece genel netlerime bakıyorum.", points: 2 } ] }, { question: "Zorlandığınız bir konuyla karşılaştığınızda ne yaparsınız?", answers: [ { text: "Farklı kaynaklardan araştırır, üzerine giderek çözmeye çalışırım.", points: 0 }, { text: "Bir süre erteleyip daha kolay konulara geçerim.", points: 1 }, { text: "Genellikle o konuyu atlarım veya moralim bozulur.", points: 2 } ] } ];
    let currentQuestionIndex = 0;
    let score = 0;
    let userAnswersText = []; 
    startBtn.addEventListener('click', startQuiz);
    function startQuiz() { startScreen.classList.add('hidden'); quizScreen.classList.remove('hidden'); currentQuestionIndex = 0; score = 0; userAnswersText = []; showQuestion(); }
    function showQuestion() { resetState(); const currentQuestion = questions[currentQuestionIndex]; questionNumberText.innerText = `Soru ${currentQuestionIndex + 1} / ${questions.length}`; questionText.innerText = currentQuestion.question; currentQuestion.answers.forEach(answer => { const button = document.createElement('button'); button.innerHTML = `<div class="p-5 rounded-xl answer-option"><p class="text-lg font-medium text-gray-700">${answer.text}</p></div>`; button.classList.add('w-full', 'text-left'); button.addEventListener('click', () => selectAnswer(answer.points, answer.text, button)); answerButtons.appendChild(button); }); updateProgressBar(); }
    function resetState() { while (answerButtons.firstChild) { answerButtons.removeChild(answerButtons.firstChild); } }
    function selectAnswer(points, answerText, selectedButton) { const allOptions = answerButtons.querySelectorAll('.answer-option'); allOptions.forEach(opt => opt.classList.remove('selected')); selectedButton.querySelector('.answer-option').classList.add('selected'); setTimeout(() => { score += points; userAnswersText.push(`Soru "${questions[currentQuestionIndex].question}": Cevap "${answerText}"`); currentQuestionIndex++; if (currentQuestionIndex < questions.length) { showQuestion(); } else { showResult(); } }, 400); }
    function updateProgressBar() { const progressPercentage = ((currentQuestionIndex) / questions.length) * 100; progressBar.style.width = `${progressPercentage}%`; }
    async function showResult() { quizScreen.classList.add('hidden'); resultScreen.classList.remove('hidden'); await getGeminiFeedback(); }
    
    // --- Gemini API Fonksiyonları ---
    async function callGemini(prompt, retries = 3, delay = 1000) {
        // ▼▼▼ BURAYI GÜNCELLEYİN ▼▼▼
        const apiKey = "AIzaSyCZANJx-xM59KSJ6KzkwUuroN3c4R1LKC8"; 
        // ▲▲▲ BURAYI GÜNCELLEYİN ▲▲▲
        
        const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=${apiKey}`;
        const payload = { contents: [{ parts: [{ text: prompt }] }], generationConfig: { temperature: 0.7, topK: 1, topP: 1, maxOutputTokens: 8192, }, };
        for (let i = 0; i < retries; i++) {
            try {
                const response = await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                const result = await response.json();
                if (result.candidates && result.candidates[0]?.content?.parts?.[0]?.text) {
                    return result.candidates[0].content.parts[0].text;
                } else { throw new Error("API'den beklenen formatta bir cevap alınamadı."); }
            } catch (error) {
                console.error(`Attempt ${i + 1} failed:`, error);
                if (i === retries - 1) { return `Üzgünüz, analiz yapılırken bir hata oluştu. Lütfen daha sonra tekrar deneyin. Hata: ${error.message}`; }
                await new Promise(res => setTimeout(res, delay * Math.pow(2, i))); 
            }
        }
    }

    async function getGeminiFeedback() {
        const answersString = userAnswersText.join('\n');
        const prompt = `Bir öğrenci, mentörlük (eğitim koçluğu) ihtiyacı olup olmadığını anlamak için bir test çözdü. Öğrencinin cevapları şunlardır:\n---\n${answersString}\n---\nBu cevaplara dayanarak, öğrenci için Markdown formatında, samimi ve cesaretlendirici bir dille kısa bir analiz ve tavsiye metni oluştur. Analizde şu adımları izle:\n1. Bir ana başlık oluştur (örn: "### Test Sonuçların ve Yol Haritan").\n2. Öğrencinin cevaplarından yola çıkarak güçlü yönlerini kısaca belirt.\n3. Geliştirebileceği alanları nazik bir dille ifade et.\n4. Son olarak, testin genel puanına göre (${score} puan) bir sonuç çıkar. Eğer puan 5 veya üzerindeyse, profesyonel bir mentörle çalışmanın neden faydalı olacağını açıkla. Eğer puan 5'in altındaysa, kendi başına nasıl daha verimli çalışabileceğine dair 2-3 somut ipucu ver.\nCevabın tamamı kısa ve öz olsun.`;
        
        const feedback = await callGemini(prompt);
        displayFeedback(feedback);
    }

    function displayFeedback(feedback) {
        resultLoading.classList.add('hidden');
        let htmlFeedback = feedback.replace(/### (.*)/g, '<h3 class="text-2xl font-bold text-gray-800 mb-4">$1</h3>').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\* (.*)/g, '<li class="mb-2 ml-4">$1</li>').replace(/^(?!<h3|<li)(.*)$/gm, (match) => { if (match.trim() === '') return ''; return `<p class="text-gray-600">${match}</p>`; }).replace(/<\/li>\s*<li/g, '</li><li'); 
        htmlFeedback = htmlFeedback.replace(/(<li.*?>.*?<\/li>)+/g, (match) => { return `<ul class="list-disc pl-5 space-y-2">${match}</ul>`; });
        resultContent.innerHTML = htmlFeedback;
        resultContent.classList.remove('hidden');
        resultContent.classList.add('prose');
        resultActions.classList.remove('hidden');
        resultActions.innerHTML = ''; 
        if (score >= 5) {
            resultYesExtra.classList.remove('hidden');
            const planButton = document.createElement('button');
            planButton.innerHTML = `✨ Bana Özel Bir Başlangıç Planı Oluştur`;
            planButton.className = 'btn-primary-theme font-bold py-3 px-6 rounded-full text-md shadow-lg hover:shadow-xl transform hover:scale-105';
            planButton.onclick = generateStudyPlan;
            resultActions.appendChild(planButton);
            const whatsappLink = document.createElement('a');
            whatsappLink.href = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(whatsappMessage)}`;
            whatsappLink.target = '_blank';
            whatsappLink.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-whatsapp inline-block -mt-1 mr-2" viewBox="0 0 16 16"><path d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/></svg>WhatsApp'tan Ulaş`;
            whatsappLink.className = 'inline-block bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-full text-md shadow-lg hover:shadow-xl transform hover:scale-105 ml-0 md:ml-4 mt-4 md:mt-0';
            resultActions.appendChild(whatsappLink);
        }
        const restartButton = document.createElement('button');
        restartButton.innerText = 'Testi Tekrar Çöz';
        restartButton.className = 'btn-secondary-theme font-bold py-3 px-6 rounded-full text-md shadow-lg hover:shadow-xl transform hover:scale-105 mt-4 w-full';
        resultActions.appendChild(restartButton);
        restartButton.onclick = () => location.reload();
    }

    async function generateStudyPlan() {
        resultContent.innerHTML = `<div class="flex flex-col items-center"><div class="loader"></div><p class="text-gray-600 mt-4">✨ Sana özel başlangıç planın oluşturuluyor...</p></div>`;
        resultActions.classList.add('hidden'); 
        const answersString = userAnswersText.join('\n');
        const prompt = `Bir öğrenci, mentörlük (eğitim koçluğu) ihtiyacı olduğunu belirten bir test çözdü. Öğrencinin zorlandığı alanlar şu cevaplarından anlaşılıyor:\n---\n${answersString}\n---\nBu öğrenci için Markdown formatında, 1 haftalık örnek bir "Başlangıç Çalışma Planı" oluştur. Plan, cesaretlendirici bir dille yazılsın ve şu unsurları içersin:\n1. Kısa bir giriş paragrafı.\n2. Her gün için (Pazartesi'den Pazar'a) sabah, öğle ve akşam olmak üzere 2-3 maddelik basit ve uygulanabilir görevler.\n3. Görevler, öğrencinin belirttiği zorluklara (hedef belirleme, programa uyma, motivasyon, analiz) yönelik olsun. Örneğin, "Hedef Belirleme Saati", "Programı Gözden Geçirme", "Küçük Mola Aktivitesi" gibi.\n4. Kısa bir kapanış ve motivasyon paragrafı.`;
        const plan = await callGemini(prompt);
        displayFeedback(plan); 
        resultActions.classList.remove('hidden'); 
    }

</script>

</body>
</html>
