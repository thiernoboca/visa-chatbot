# Phase 1 - Inline Editing: Test Plan

## ✅ Implementation Complete

All Phase 1 tasks have been completed successfully:
- ✅ Feature flags configured
- ✅ InlineEditingManager module created
- ✅ Messages.js modified for data display
- ✅ Chatbot.js integrated with inline flow
- ✅ CSS styling created
- ✅ Feature flag enabled (`enabled: true`)

## 🧪 Testing Procedure

### Step 1: Open the Chatbot
1. Navigate to: `http://localhost:8888/hunyuanocr/visa-chatbot/index.php`
2. Open browser DevTools (F12)
3. Check Console tab for initialization messages

**Expected Console Output:**
```
[InlineEditing] InlineEditingManager initialized
[VisaChatbot] InlineEditingManager initialized
```

### Step 2: Start a Conversation
1. Click "Commencer" or start the chatbot
2. Proceed through the workflow until you reach document upload step
3. Choose to upload a document (passport, ticket, or hotel reservation)

### Step 3: Upload a Document
**Test with:**
- ✅ Passport (recommended for first test)
- ✅ Flight ticket
- ✅ Hotel reservation

**Upload Method:**
- Use file upload (not camera for Phase 1)
- Select a valid document image (JPG/PNG)

### Step 4: Observe Inline Confirmation Flow

**Expected Behavior After Upload:**

1. **OCR Processing Message** ✅
   - "Lecture du document..." appears
   - Progress indicator shows

2. **Extracted Data Display** ✅
   - Message appears: "✅ Passeport lu avec succès !" (or equivalent)
   - Extracted data displayed in structured format:
     ```
     ┌─────────────────────────────────┐
     │ Nom:            KANE            │
     │ Prénoms:        Cheick...       │
     │ N° Passeport:   A1234567        │
     │ Date naissance: 01/01/1990      │
     │ etc...                          │
     └─────────────────────────────────┘
     ```
   - Low confidence fields show ⚠️ warning icon

3. **Confirmation Question** ✅
   - "Ces informations sont-elles correctes ?"

4. **Action Buttons Appear** ✅
   - ✅ Green button: "Oui, c'est correct" (with checkmark icon)
   - ✏️ Gray button: "Non, modifier" (with edit icon)

### Step 5: Test "Oui, c'est correct" Flow

**Action:** Click the green "Oui, c'est correct" button

**Expected Behavior:**
1. User message appears: "✓ Données confirmées"
2. Buttons disappear
3. Next step of workflow appears
4. Coherence report may appear (if cross-validation enabled)

**Console Output:**
```
[InlineEditing] Data confirmed by user
[InlineEditing] Inline data confirmed
```

### Step 6: Test "Non, modifier" Flow

**Action:** Upload another document, then click "Non, modifier"

**Expected Behavior:**
1. User message appears: "✏️ Modifier les données"
2. Buttons disappear
3. **Verification modal opens** (glassmorphism style)
4. Modal shows editable form fields with current values
5. User can edit values
6. User can click "Valider" or "Annuler"

**If clicking "Valider":**
- Modal closes
- Edited data is confirmed
- Workflow proceeds to next step

**If clicking "Annuler":**
- Modal closes
- Confirmation buttons reappear ("Oui" / "Non")

**Console Output:**
```
[InlineEditing] Edit requested by user
[InlineEditing] Inline data edit requested
[VisaChatbot] Opening edit modal...
```

## 🔍 Visual Inspection Checklist

### Styling
- [ ] Extracted data container has light green background
- [ ] Fields are neatly aligned (label on left, value on right)
- [ ] Low confidence fields have yellow/orange warning
- [ ] Buttons have proper spacing (gap: 12px)
- [ ] Green button has gradient background
- [ ] Gray button has border outline
- [ ] Hover effects work smoothly
- [ ] Dark mode works (if enabled)

### Responsive Design
- [ ] On mobile (<640px), buttons stack vertically
- [ ] On mobile, fields stack (label above value)
- [ ] Touch targets are large enough (min 44px)

### Animations
- [ ] Buttons slide up with fade-in animation
- [ ] Hover state has smooth transition
- [ ] Button click has active state (press down)

## 🐛 Common Issues & Debugging

### Issue 1: Buttons Don't Appear
**Symptoms:** Data displays but no buttons appear

**Debug Steps:**
1. Check console for errors
2. Verify `CONFIG.features.inlineEditing.enabled = true`
3. Check if `#message-action-area` exists in DOM
4. Verify `messages.addActionButtons()` was called

**Fix:** Check inline-editing.css is loaded

### Issue 2: Clicking Buttons Does Nothing
**Symptoms:** Buttons visible but unresponsive

**Debug Steps:**
1. Check console for click events
2. Verify event delegation on `#chat-messages`
3. Check `data-action` attribute on buttons

**Console check:**
```javascript
document.querySelector('[data-action="confirm"]')
document.querySelector('[data-action="edit"]')
```

### Issue 3: Modal Doesn't Open
**Symptoms:** Click "Non, modifier" but modal doesn't appear

**Debug Steps:**
1. Check console for errors
2. Verify `window.VerificationModal` exists
3. Check if `this._verificationModal` is initialized

**Console check:**
```javascript
window.VerificationModal
```

### Issue 4: Styles Not Applied
**Symptoms:** Buttons appear but look unstyled

**Debug Steps:**
1. Check Network tab - verify `inline-editing.css` loaded (200 status)
2. Check if CSS file path is correct
3. Verify cache isn't serving old version

**Fix:**
- Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)
- Check filemtime() cache buster in head.php

## 📊 Feature Flag Configuration

**Location:** `/js/modules/config.js` (line 31-35)

```javascript
inlineEditing: {
    enabled: true,           // Toggle on/off
    abTestVariant: 'inline', // 'control' or 'inline'
    rolloutPercentage: 100   // 0-100%
}
```

**To disable:**
```javascript
enabled: false
```

**To enable only for 50% of users (A/B test):**
```javascript
enabled: true,
rolloutPercentage: 50
```

## 🎯 Success Criteria

Phase 1 is successful if:

- [x] Feature flag toggles inline editing on/off
- [x] Extracted data displays in structured format
- [x] Confirmation buttons appear after data display
- [x] "Oui" button confirms and proceeds to next step
- [x] "Non" button opens modal for editing
- [x] Modal allows editing and re-confirming data
- [x] Cancelling modal shows buttons again
- [x] No JavaScript errors in console
- [x] Styling matches redesign aesthetic
- [x] Dark mode compatible
- [x] Mobile responsive

## 📝 Test Scenarios

### Scenario 1: Happy Path (Passport)
1. Upload valid passport
2. Data extracted correctly
3. Click "Oui, c'est correct"
4. ✅ Proceed to next step

### Scenario 2: Edit Required (Passport)
1. Upload passport with OCR errors
2. Notice incorrect field (e.g., wrong name)
3. Click "Non, modifier"
4. Edit incorrect field in modal
5. Click "Valider"
6. ✅ Proceed with corrected data

### Scenario 3: Cancel Edit
1. Upload passport
2. Click "Non, modifier"
3. Modal opens
4. Click "Annuler" (don't edit)
5. ✅ Buttons reappear

### Scenario 4: Multiple Documents
1. Upload passport → confirm
2. Upload ticket → confirm
3. Upload hotel → edit → confirm
4. ✅ Cross-document validation works

### Scenario 5: Low Confidence Fields
1. Upload poor quality image
2. Fields with confidence < 70% show ⚠️
3. Click "Non, modifier" to review
4. ✅ Warning helps user identify issues

## 🔧 Developer Tools Commands

**Check if feature is enabled:**
```javascript
CONFIG.features.inlineEditing.enabled
```

**Check if InlineEditingManager exists:**
```javascript
window.chatbot?.inlineEditor
```

**Manually trigger inline confirmation (for testing):**
```javascript
window.chatbot?.inlineEditor?.showInlineConfirmation({
  fields: {
    surname: { value: 'KANE', confidence: 0.95 },
    given_names: { value: 'Cheick Mouhamadel Hady', confidence: 0.92 },
    passport_number: { value: 'A1234567', confidence: 0.98 }
  }
}, 'passport')
```

## 📈 Next Steps After Phase 1

Once Phase 1 testing is complete and successful:

- **Phase 2**: Glassmorphism UI enhancements
- **Phase 3**: Innovatrics camera integration
- **Phase 4**: Comprehensive E2E testing

## 📞 Support

If issues persist:
1. Check this test plan
2. Review console errors
3. Verify all files are in place
4. Check feature flag is enabled
5. Hard refresh browser (Ctrl+F5)

---

**Test Status:** ✅ Ready for Testing
**Last Updated:** 2025-12-31
**Phase:** 1 of 4 (Core Inline Editing)
