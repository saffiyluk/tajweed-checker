// Enhanced filtering for history page
if (document.getElementById("ruleFilter")) {
    const ruleFilter = document.getElementById("ruleFilter");
    const statusFilter = document.getElementById("statusFilter");
    const cards = document.querySelectorAll(".history-card");

    function filterCards() {
        const ruleValue = ruleFilter.value;
        const statusValue = statusFilter.value;

        cards.forEach((card) => {
            const cardRule = card.dataset.rule;
            const cardStatus = card.dataset.status;
            let show = true;

            if (ruleValue && cardRule !== ruleValue) show = false;
            if (statusValue && cardStatus !== statusValue) show = false;

            card.style.display = show ? "block" : "none";
        });
    }

    ruleFilter.addEventListener("change", filterCards);
    statusFilter.addEventListener("change", filterCards);
}

// Enhanced audio recording with visualization
class AudioRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.recordingTime = 0;
        this.timerInterval = null;
        this.audioContext = null;
        this.analyser = null;
        this.canvas = null;
        this.canvasCtx = null;
    }

    async start() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            this.setupVisualizer(stream);

            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];

            this.mediaRecorder.ondataavailable = (e) => {
                this.audioChunks.push(e.data);
            };

            this.startTimer();
            this.mediaRecorder.start();
            return true;
        } catch (error) {
            console.error("Recording error:", error);
            return false;
        }
    }

    stop() {
        if (this.mediaRecorder) {
            this.mediaRecorder.stop();
            this.stopTimer();
            this.stopVisualizer();

            const blob = new Blob(this.audioChunks, { type: "audio/webm" });
            return blob;
        }
        return null;
    }

    startTimer() {
        this.recordingTime = 0;
        this.timerInterval = setInterval(() => {
            this.recordingTime++;
            const minutes = Math.floor(this.recordingTime / 60);
            const seconds = this.recordingTime % 60;
            document.getElementById("timer").textContent =
                `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
        }, 1000);
    }

    stopTimer() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
    }

    setupVisualizer(stream) {
        this.audioContext = new (
            window.AudioContext || window.webkitAudioContext
        )();
        this.analyser = this.audioContext.createAnalyser();
        const source = this.audioContext.createMediaStreamSource(stream);
        source.connect(this.analyser);

        this.canvas = document.getElementById("waveform");
        if (this.canvas) {
            this.canvasCtx = this.canvas.getContext("2d");
            this.drawVisualizer();
            document.getElementById("visualizer").style.display = "block";
        }
    }

    drawVisualizer() {
        if (!this.analyser || !this.canvasCtx) return;

        requestAnimationFrame(() => this.drawVisualizer());

        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        this.analyser.getByteTimeDomainData(dataArray);

        this.canvasCtx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.canvasCtx.lineWidth = 2;
        this.canvasCtx.strokeStyle = "#3b82f6";

        this.canvasCtx.beginPath();

        const sliceWidth = (this.canvas.width * 1.0) / bufferLength;
        let x = 0;

        for (let i = 0; i < bufferLength; i++) {
            const v = dataArray[i] / 128.0;
            const y = (v * this.canvas.height) / 2;

            if (i === 0) {
                this.canvasCtx.moveTo(x, y);
            } else {
                this.canvasCtx.lineTo(x, y);
            }

            x += sliceWidth;
        }

        this.canvasCtx.lineTo(this.canvas.width, this.canvas.height / 2);
        this.canvasCtx.stroke();
    }

    stopVisualizer() {
        if (this.audioContext) {
            this.audioContext.close();
        }
        document.getElementById("visualizer").style.display = "none";
    }
}

// Initialize audio recorder if on recording page
if (document.getElementById("startBtn")) {
    const recorder = new AudioRecorder();
    const startBtn = document.getElementById("startBtn");
    const stopBtn = document.getElementById("stopBtn");
    const pauseBtn = document.getElementById("pauseBtn");

    startBtn.addEventListener("click", async () => {
        const started = await recorder.start();
        if (started) {
            startBtn.disabled = true;
            stopBtn.disabled = false;
            pauseBtn.disabled = false;
        }
    });

    stopBtn.addEventListener("click", () => {
        const blob = recorder.stop();
        startBtn.disabled = false;
        stopBtn.disabled = true;
        pauseBtn.disabled = true;
        // Handle blob...
    });
}
