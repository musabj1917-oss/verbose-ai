/**
 * Verbose AI - Retro Terminal UI SFX Engine
 * Procedural 8-bit blips, mechanical keyboard key-ticks, and chimes.
 * Full Master Volume control and persistence.
 */

class RetroAudioEngine {
  constructor() {
    this.ctx = null;
    this.masterGain = null;
    this.enabled = localStorage.getItem('verbose_sfx') !== 'false';
    const savedVol = localStorage.getItem('verbose_sfx_volume');
    this.volume = savedVol !== null ? parseFloat(savedVol) : 0.6;
  }

  init() {
    if (!this.ctx && typeof window !== 'undefined') {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (AudioCtx) {
        this.ctx = new AudioCtx();
        this.masterGain = this.ctx.createGain();
        this.masterGain.gain.setValueAtTime(this.enabled ? this.volume : 0, this.ctx.currentTime);
        this.masterGain.connect(this.ctx.destination);
      }
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  }

  setVolume(vol) {
    this.volume = Math.max(0, Math.min(1, parseFloat(vol)));
    localStorage.setItem('verbose_sfx_volume', this.volume.toString());
    if (this.masterGain && this.ctx) {
      this.masterGain.gain.setValueAtTime(this.enabled ? this.volume : 0, this.ctx.currentTime);
    }
  }

  getVolume() {
    return this.volume;
  }

  setEnabled(val) {
    this.enabled = !!val;
    localStorage.setItem('verbose_sfx', this.enabled ? 'true' : 'false');
    if (this.masterGain && this.ctx) {
      this.masterGain.gain.setValueAtTime(this.enabled ? this.volume : 0, this.ctx.currentTime);
    }
    if (this.enabled) this.playBlip(600, 0.05);
  }

  getDestination() {
    this.init();
    return this.masterGain || this.ctx.destination;
  }

  playKeyTick() {
    if (!this.enabled || this.volume === 0) return;
    this.init();
    if (!this.ctx) return;

    const t = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = 'square';
    const freq = 1200 + Math.random() * 300;
    osc.frequency.setValueAtTime(freq, t);
    osc.frequency.exponentialRampToValueAtTime(300, t + 0.012);

    gain.gain.setValueAtTime(0.04, t);
    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.012);

    osc.connect(gain);
    gain.connect(this.getDestination());

    osc.start(t);
    osc.stop(t + 0.012);
  }

  playBlip(freq = 520, duration = 0.04) {
    if (!this.enabled || this.volume === 0) return;
    this.init();
    if (!this.ctx) return;

    const t = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = 'square';
    osc.frequency.setValueAtTime(freq, t);
    osc.frequency.setValueAtTime(freq * 1.5, t + duration * 0.5);

    gain.gain.setValueAtTime(0.06, t);
    gain.gain.exponentialRampToValueAtTime(0.001, t + duration);

    osc.connect(gain);
    gain.connect(this.getDestination());

    osc.start(t);
    osc.stop(t + duration);
  }

  playSend() {
    if (!this.enabled || this.volume === 0) return;
    this.init();
    if (!this.ctx) return;

    const t = this.ctx.currentTime;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();

    osc.type = 'square';
    osc.frequency.setValueAtTime(320, t);
    osc.frequency.exponentialRampToValueAtTime(960, t + 0.07);

    gain.gain.setValueAtTime(0.07, t);
    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.07);

    osc.connect(gain);
    gain.connect(this.getDestination());

    osc.start(t);
    osc.stop(t + 0.07);
  }

  playReplyChime() {
    if (!this.enabled || this.volume === 0) return;
    this.init();
    if (!this.ctx) return;

    const t = this.ctx.currentTime;

    const o1 = this.ctx.createOscillator();
    const g1 = this.ctx.createGain();
    o1.type = 'square';
    o1.frequency.setValueAtTime(587.33, t);
    g1.gain.setValueAtTime(0.05, t);
    g1.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
    o1.connect(g1);
    g1.connect(this.getDestination());
    o1.start(t);
    o1.stop(t + 0.08);

    const o2 = this.ctx.createOscillator();
    const g2 = this.ctx.createGain();
    o2.type = 'square';
    o2.frequency.setValueAtTime(880.00, t + 0.06);
    g2.gain.setValueAtTime(0.06, t + 0.06);
    g2.gain.exponentialRampToValueAtTime(0.001, t + 0.16);
    o2.connect(g2);
    g2.connect(this.getDestination());
    o2.start(t + 0.06);
    o2.stop(t + 0.16);
  }
}

window.SFX = new RetroAudioEngine();
