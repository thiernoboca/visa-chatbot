/**
 * Electronic Signature Module
 * Handles signature capture via canvas
 *
 * @module signature
 * @version 1.0.0
 *
 * Features:
 * - Canvas-based signature capture
 * - Touch and mouse support
 * - Signature validation
 * - Export to image data
 */

// =============================================================================
// CONSTANTS
// =============================================================================

/**
 * Default canvas configuration
 */
const DEFAULT_CONFIG = {
    width: 400,
    height: 150,
    backgroundColor: '#ffffff',
    strokeColor: '#000000',
    strokeWidth: 2,
    minStrokeLength: 50, // Minimum total stroke length to be considered valid
    smoothing: true
};

/**
 * Signature status
 */
export const SignatureStatus = {
    EMPTY: 'empty',
    DRAWING: 'drawing',
    COMPLETE: 'complete',
    VALIDATED: 'validated'
};

// =============================================================================
// SIGNATURE PAD CLASS
// =============================================================================

export class SignaturePad {
    constructor(canvas, options = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.config = { ...DEFAULT_CONFIG, ...options };
        this.language = options.language || 'fr';

        // State
        this.isDrawing = false;
        this.lastPoint = null;
        this.strokes = [];
        this.currentStroke = [];
        this.totalStrokeLength = 0;
        this.status = SignatureStatus.EMPTY;

        // Callbacks
        this.onChange = options.onChange || (() => {});

        // Initialize
        this.init();
    }

    /**
     * Initialize the signature pad
     */
    init() {
        // Set canvas size
        this.canvas.width = this.config.width;
        this.canvas.height = this.config.height;

        // Set canvas style
        this.canvas.style.touchAction = 'none'; // Prevent scrolling on touch
        this.canvas.style.cursor = 'crosshair';

        // Clear and set background
        this.clear();

        // Bind events
        this.bindEvents();
    }

    /**
     * Bind touch and mouse events
     */
    bindEvents() {
        // Mouse events
        this.canvas.addEventListener('mousedown', this.handleStart.bind(this));
        this.canvas.addEventListener('mousemove', this.handleMove.bind(this));
        this.canvas.addEventListener('mouseup', this.handleEnd.bind(this));
        this.canvas.addEventListener('mouseleave', this.handleEnd.bind(this));

        // Touch events
        this.canvas.addEventListener('touchstart', this.handleTouchStart.bind(this), { passive: false });
        this.canvas.addEventListener('touchmove', this.handleTouchMove.bind(this), { passive: false });
        this.canvas.addEventListener('touchend', this.handleTouchEnd.bind(this));
        this.canvas.addEventListener('touchcancel', this.handleEnd.bind(this));
    }

    /**
     * Get point from mouse event
     */
    getMousePoint(e) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
            time: Date.now()
        };
    }

    /**
     * Get point from touch event
     */
    getTouchPoint(e) {
        const rect = this.canvas.getBoundingClientRect();
        const touch = e.touches[0];
        return {
            x: touch.clientX - rect.left,
            y: touch.clientY - rect.top,
            time: Date.now()
        };
    }

    /**
     * Handle drawing start
     */
    handleStart(e) {
        e.preventDefault();
        this.isDrawing = true;
        this.status = SignatureStatus.DRAWING;
        this.currentStroke = [];

        const point = this.getMousePoint(e);
        this.lastPoint = point;
        this.currentStroke.push(point);

        this.ctx.beginPath();
        this.ctx.moveTo(point.x, point.y);
    }

    /**
     * Handle touch start
     */
    handleTouchStart(e) {
        e.preventDefault();
        if (e.touches.length === 1) {
            this.isDrawing = true;
            this.status = SignatureStatus.DRAWING;
            this.currentStroke = [];

            const point = this.getTouchPoint(e);
            this.lastPoint = point;
            this.currentStroke.push(point);

            this.ctx.beginPath();
            this.ctx.moveTo(point.x, point.y);
        }
    }

    /**
     * Handle drawing move
     */
    handleMove(e) {
        if (!this.isDrawing) return;
        e.preventDefault();

        const point = this.getMousePoint(e);
        this.drawLine(this.lastPoint, point);
        this.currentStroke.push(point);
        this.lastPoint = point;
    }

    /**
     * Handle touch move
     */
    handleTouchMove(e) {
        if (!this.isDrawing) return;
        e.preventDefault();

        if (e.touches.length === 1) {
            const point = this.getTouchPoint(e);
            this.drawLine(this.lastPoint, point);
            this.currentStroke.push(point);
            this.lastPoint = point;
        }
    }

    /**
     * Handle drawing end
     */
    handleEnd(e) {
        if (!this.isDrawing) return;

        this.isDrawing = false;

        if (this.currentStroke.length > 0) {
            this.strokes.push([...this.currentStroke]);
            this.totalStrokeLength += this.calculateStrokeLength(this.currentStroke);
        }

        this.currentStroke = [];
        this.lastPoint = null;

        if (this.strokes.length > 0) {
            this.status = SignatureStatus.COMPLETE;
        }

        this.onChange('strokeEnd', this.getState());
    }

    /**
     * Handle touch end
     */
    handleTouchEnd(e) {
        e.preventDefault();
        this.handleEnd(e);
    }

    /**
     * Draw line between two points
     */
    drawLine(from, to) {
        this.ctx.strokeStyle = this.config.strokeColor;
        this.ctx.lineWidth = this.config.strokeWidth;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';

        if (this.config.smoothing) {
            // Quadratic curve for smoother lines
            const midX = (from.x + to.x) / 2;
            const midY = (from.y + to.y) / 2;
            this.ctx.quadraticCurveTo(from.x, from.y, midX, midY);
        } else {
            this.ctx.lineTo(to.x, to.y);
        }

        this.ctx.stroke();
    }

    /**
     * Calculate stroke length
     */
    calculateStrokeLength(stroke) {
        let length = 0;
        for (let i = 1; i < stroke.length; i++) {
            const dx = stroke[i].x - stroke[i - 1].x;
            const dy = stroke[i].y - stroke[i - 1].y;
            length += Math.sqrt(dx * dx + dy * dy);
        }
        return length;
    }

    /**
     * Clear the signature
     */
    clear() {
        this.ctx.fillStyle = this.config.backgroundColor;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // Draw signature line hint
        this.ctx.strokeStyle = '#e0e0e0';
        this.ctx.lineWidth = 1;
        this.ctx.beginPath();
        this.ctx.moveTo(20, this.canvas.height - 30);
        this.ctx.lineTo(this.canvas.width - 20, this.canvas.height - 30);
        this.ctx.stroke();

        // Reset state
        this.strokes = [];
        this.currentStroke = [];
        this.totalStrokeLength = 0;
        this.isDrawing = false;
        this.lastPoint = null;
        this.status = SignatureStatus.EMPTY;

        this.onChange('cleared', this.getState());
    }

    /**
     * Check if signature is empty
     */
    isEmpty() {
        return this.strokes.length === 0;
    }

    /**
     * Validate the signature
     */
    validate() {
        const issues = [];

        if (this.isEmpty()) {
            issues.push({
                type: 'EMPTY',
                message: this.t({
                    fr: 'Veuillez signer avant de continuer',
                    en: 'Please sign before continuing'
                })
            });
        }

        if (this.totalStrokeLength < this.config.minStrokeLength) {
            issues.push({
                type: 'TOO_SHORT',
                message: this.t({
                    fr: 'La signature semble trop courte. Veuillez signer de manière lisible.',
                    en: 'The signature appears too short. Please sign legibly.'
                })
            });
        }

        if (this.strokes.length < 2) {
            issues.push({
                type: 'TOO_SIMPLE',
                message: this.t({
                    fr: 'La signature semble trop simple. Veuillez signer normalement.',
                    en: 'The signature appears too simple. Please sign normally.'
                })
            });
        }

        const isValid = issues.length === 0;

        if (isValid) {
            this.status = SignatureStatus.VALIDATED;
        }

        return {
            valid: isValid,
            issues,
            stats: {
                strokeCount: this.strokes.length,
                totalLength: this.totalStrokeLength,
                pointCount: this.strokes.reduce((sum, s) => sum + s.length, 0)
            }
        };
    }

    /**
     * Export signature as data URL
     */
    toDataURL(type = 'image/png', quality = 0.92) {
        if (this.isEmpty()) return null;
        return this.canvas.toDataURL(type, quality);
    }

    /**
     * Export signature as Blob
     */
    toBlob(callback, type = 'image/png', quality = 0.92) {
        if (this.isEmpty()) {
            callback(null);
            return;
        }
        this.canvas.toBlob(callback, type, quality);
    }

    /**
     * Get signature dimensions (bounding box)
     */
    getBoundingBox() {
        if (this.isEmpty()) return null;

        let minX = Infinity, minY = Infinity;
        let maxX = -Infinity, maxY = -Infinity;

        for (const stroke of this.strokes) {
            for (const point of stroke) {
                minX = Math.min(minX, point.x);
                minY = Math.min(minY, point.y);
                maxX = Math.max(maxX, point.x);
                maxY = Math.max(maxY, point.y);
            }
        }

        return {
            x: minX,
            y: minY,
            width: maxX - minX,
            height: maxY - minY
        };
    }

    /**
     * Get current state
     */
    getState() {
        return {
            isEmpty: this.isEmpty(),
            status: this.status,
            strokeCount: this.strokes.length,
            totalLength: this.totalStrokeLength
        };
    }

    /**
     * Translation helper
     */
    t(obj) {
        if (typeof obj === 'string') return obj;
        return obj[this.language] || obj.fr || obj.en || '';
    }

    /**
     * Set language
     */
    setLanguage(lang) {
        this.language = lang === 'en' ? 'en' : 'fr';
    }

    /**
     * Resize canvas (preserves signature)
     */
    resize(width, height) {
        // Save current image
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);

        // Resize
        this.canvas.width = width;
        this.canvas.height = height;
        this.config.width = width;
        this.config.height = height;

        // Restore image (scaled)
        this.ctx.putImageData(imageData, 0, 0);
    }

    /**
     * Undo last stroke
     */
    undo() {
        if (this.strokes.length === 0) return;

        const removedStroke = this.strokes.pop();
        this.totalStrokeLength -= this.calculateStrokeLength(removedStroke);

        // Redraw all remaining strokes
        this.redraw();

        if (this.strokes.length === 0) {
            this.status = SignatureStatus.EMPTY;
        }

        this.onChange('undo', this.getState());
    }

    /**
     * Redraw all strokes
     */
    redraw() {
        // Clear canvas
        this.ctx.fillStyle = this.config.backgroundColor;
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // Draw signature line hint
        this.ctx.strokeStyle = '#e0e0e0';
        this.ctx.lineWidth = 1;
        this.ctx.beginPath();
        this.ctx.moveTo(20, this.canvas.height - 30);
        this.ctx.lineTo(this.canvas.width - 20, this.canvas.height - 30);
        this.ctx.stroke();

        // Redraw all strokes
        this.ctx.strokeStyle = this.config.strokeColor;
        this.ctx.lineWidth = this.config.strokeWidth;
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';

        for (const stroke of this.strokes) {
            if (stroke.length === 0) continue;

            this.ctx.beginPath();
            this.ctx.moveTo(stroke[0].x, stroke[0].y);

            for (let i = 1; i < stroke.length; i++) {
                if (this.config.smoothing) {
                    const midX = (stroke[i - 1].x + stroke[i].x) / 2;
                    const midY = (stroke[i - 1].y + stroke[i].y) / 2;
                    this.ctx.quadraticCurveTo(stroke[i - 1].x, stroke[i - 1].y, midX, midY);
                } else {
                    this.ctx.lineTo(stroke[i].x, stroke[i].y);
                }
            }

            this.ctx.stroke();
        }
    }

    /**
     * Destroy and cleanup
     */
    destroy() {
        // Remove event listeners
        this.canvas.removeEventListener('mousedown', this.handleStart);
        this.canvas.removeEventListener('mousemove', this.handleMove);
        this.canvas.removeEventListener('mouseup', this.handleEnd);
        this.canvas.removeEventListener('mouseleave', this.handleEnd);
        this.canvas.removeEventListener('touchstart', this.handleTouchStart);
        this.canvas.removeEventListener('touchmove', this.handleTouchMove);
        this.canvas.removeEventListener('touchend', this.handleTouchEnd);
        this.canvas.removeEventListener('touchcancel', this.handleEnd);

        this.strokes = [];
        this.currentStroke = [];
    }
}

// =============================================================================
// SIGNATURE MANAGER CLASS
// =============================================================================

export class SignatureManager {
    constructor(options = {}) {
        this.language = options.language || 'fr';
        this.pad = null;
        this.signatureData = null;
        this.signerInfo = {};
        this.timestamp = null;
        this.onChange = options.onChange || (() => {});
    }

    /**
     * Initialize with a canvas element
     */
    initialize(canvasElement, config = {}) {
        this.pad = new SignaturePad(canvasElement, {
            ...config,
            language: this.language,
            onChange: (event, state) => {
                this.onChange('pad', { event, state });
            }
        });
    }

    /**
     * Set signer information
     */
    setSignerInfo(info) {
        this.signerInfo = {
            name: info.name || '',
            email: info.email || '',
            phone: info.phone || ''
        };
    }

    /**
     * Capture and finalize signature
     */
    capture() {
        if (!this.pad) {
            throw new Error('Signature pad not initialized');
        }

        const validation = this.pad.validate();
        if (!validation.valid) {
            return {
                success: false,
                validation
            };
        }

        this.timestamp = new Date().toISOString();
        this.signatureData = this.pad.toDataURL();

        return {
            success: true,
            validation,
            signature: {
                data: this.signatureData,
                timestamp: this.timestamp,
                signer: this.signerInfo,
                boundingBox: this.pad.getBoundingBox()
            }
        };
    }

    /**
     * Get signature for PDF inclusion
     */
    getSignatureForPDF() {
        if (!this.signatureData) return null;

        return {
            image: this.signatureData,
            timestamp: this.timestamp,
            signer: this.signerInfo.name,
            dimensions: this.pad.getBoundingBox()
        };
    }

    /**
     * Clear signature
     */
    clear() {
        if (this.pad) {
            this.pad.clear();
        }
        this.signatureData = null;
        this.timestamp = null;
    }

    /**
     * Undo last stroke
     */
    undo() {
        if (this.pad) {
            this.pad.undo();
        }
    }

    /**
     * Check if has valid signature
     */
    hasSignature() {
        return this.signatureData !== null;
    }

    /**
     * Get state
     */
    getState() {
        return {
            hasSignature: this.hasSignature(),
            padState: this.pad ? this.pad.getState() : null,
            signerInfo: this.signerInfo,
            timestamp: this.timestamp
        };
    }

    /**
     * Set language
     */
    setLanguage(lang) {
        this.language = lang === 'en' ? 'en' : 'fr';
        if (this.pad) {
            this.pad.setLanguage(lang);
        }
    }

    /**
     * Destroy
     */
    destroy() {
        if (this.pad) {
            this.pad.destroy();
            this.pad = null;
        }
        this.signatureData = null;
        this.timestamp = null;
        this.signerInfo = {};
    }
}

// =============================================================================
// FACTORY FUNCTION
// =============================================================================

/**
 * Create a signature pad on a canvas element
 */
export function createSignaturePad(canvasElement, options = {}) {
    return new SignaturePad(canvasElement, options);
}

// =============================================================================
// SINGLETON EXPORT
// =============================================================================

export const signatureManager = new SignatureManager();

export default signatureManager;
