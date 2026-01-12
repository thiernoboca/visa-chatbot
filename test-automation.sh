#!/bin/bash
# Test Automation Script for Phase 1 - Inline Editing
# Tests the chatbot workflow with GEZAHEGN MOGES documents

echo "=================================================="
echo "🧪 Phase 1 - Inline Editing Test Automation"
echo "=================================================="
echo ""
echo "Test Subject: GEZAHEGN MOGES EJIGU"
echo "Documents: Passport, Flight Ticket, Hotel, Vaccination"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test documents path
DOCS_PATH="/Users/cheickmouhamedelhadykane/Downloads/test"

# Check if documents exist
echo "📂 Checking test documents..."
echo ""

if [ -f "$DOCS_PATH/passportpassport-scan.pdf" ]; then
    echo -e "${GREEN}✓${NC} Passport: passportpassport-scan.pdf (1.2MB)"
else
    echo -e "${RED}✗${NC} Passport: NOT FOUND"
fi

if [ -f "$DOCS_PATH/billetelectronic-ticket-receipt-december-28-for-mr-gezahegn-mogesejigu.pdf" ]; then
    echo -e "${GREEN}✓${NC} Flight Ticket: billetelectronic-ticket...pdf (133KB)"
else
    echo -e "${RED}✗${NC} Flight Ticket: NOT FOUND"
fi

if [ -f "$DOCS_PATH/hotelgmail-thanks-your-booking-is-confirmed-at-appartement-1-a-3-pieces-equipe-cosy-calme-aigle.pdf" ]; then
    echo -e "${GREEN}✓${NC} Hotel: hotelgmail-thanks-your-booking...pdf (426KB)"
else
    echo -e "${RED}✗${NC} Hotel: NOT FOUND"
fi

if [ -f "$DOCS_PATH/vaccinationyellow-faver-certificate-gezahegn-moges.pdf" ]; then
    echo -e "${GREEN}✓${NC} Vaccination: vaccinationyellow-faver...pdf (274KB)"
else
    echo -e "${RED}✗${NC} Vaccination: NOT FOUND"
fi

if [ -f "$DOCS_PATH/gezahegn-moges-20251221-175604-694834b4e6da1-passport-photo.jpg" ]; then
    echo -e "${GREEN}✓${NC} Passport Photo: gezahegn-moges...jpg (34KB)"
else
    echo -e "${RED}✗${NC} Passport Photo: NOT FOUND"
fi

if [ -f "$DOCS_PATH/ordremissioninvitation-letter-gezahegn-moges-ejigu.pdf" ]; then
    echo -e "${GREEN}✓${NC} Invitation Letter: ordremission...pdf (314KB)"
else
    echo -e "${RED}✗${NC} Invitation Letter: NOT FOUND"
fi

echo ""
echo "=================================================="
echo "📊 Expected Data to be Extracted"
echo "=================================================="
echo ""

echo "🛂 PASSPORT:"
echo "   Surname: EJIGU"
echo "   Given Names: GEZAHEGN MOGES"
echo "   Passport No: EQ1799898"
echo "   DOB: 22/08/1995"
echo "   Nationality: ETHIOPIAN (ETH)"
echo "   Expiry: 16/09/2030"
echo ""

echo "✈️ FLIGHT TICKET:"
echo "   Airline: Ethiopian Airlines"
echo "   Ticket No: 0712157308494"
echo "   Passenger: EJIGU/GEZAHEGN MOGES"
echo "   Booking Ref: KTKPJV"
echo "   Outbound: ET 935 - ADD→ABJ - 28/12/2025"
echo "   Return: ET 513 - ABJ→ADD - 25/01/2026"
echo ""

echo "🏨 HOTEL:"
echo "   Name: Appartement 1 à 3 pièces Equipé Cosy Calme"
echo "   Guest: Gezahegn Moges"
echo "   Confirmation: 5628305412"
echo "   Check-in: 28/12/2025"
echo "   Check-out: 29/12/2025"
echo "   Location: Yamoussoukro, Côte d'Ivoire"
echo ""

echo "=================================================="
echo "🧪 Test Scenarios"
echo "=================================================="
echo ""

echo "1. ✅ Upload passport → Verify inline confirmation"
echo "2. ✅ Click 'Oui, c'est correct' → Verify next step"
echo "3. ✏️  Upload passport again → Click 'Non, modifier'"
echo "4. ✏️  Edit field → Click 'Valider'"
echo "5. ❌ Edit field → Click 'Annuler'"
echo "6. ✈️  Upload flight ticket → Verify extraction"
echo "7. 🏨 Upload hotel → Verify extraction"
echo "8. 💉 Upload vaccination → Verify extraction"
echo "9. 🔍 Cross-document validation (name consistency)"
echo "10. 🌙 Dark mode compatibility check"
echo "11. 📱 Responsive mobile check"
echo ""

echo "=================================================="
echo "🚀 Manual Testing Instructions"
echo "=================================================="
echo ""
echo "Open browser and navigate to:"
echo -e "${YELLOW}http://localhost:8888/hunyuanocr/visa-chatbot/index.php${NC}"
echo ""
echo "Press F12 to open DevTools Console"
echo ""
echo "Expected console output:"
echo -e "${GREEN}[InlineEditing] InlineEditingManager initialized${NC}"
echo -e "${GREEN}[VisaChatbot] InlineEditingManager initialized${NC}"
echo ""
echo "Follow the test scenarios above and record results in:"
echo -e "${YELLOW}/Applications/MAMP/htdocs/hunyuanocr/visa-chatbot/TEST-RESULTS.md${NC}"
echo ""
echo "=================================================="

# Check if feature flag is enabled
echo "🔧 Checking feature flag status..."
echo ""

CONFIG_FILE="/Applications/MAMP/htdocs/hunyuanocr/visa-chatbot/js/modules/config.js"

if grep -q "enabled: true" "$CONFIG_FILE"; then
    echo -e "${GREEN}✓ Inline editing feature is ENABLED${NC}"
else
    echo -e "${RED}✗ Inline editing feature is DISABLED${NC}"
    echo "  To enable, edit $CONFIG_FILE and set enabled: true"
fi

echo ""
echo "=================================================="
echo "✨ Ready to Test!"
echo "=================================================="
