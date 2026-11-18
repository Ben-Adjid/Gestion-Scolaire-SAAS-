// Fonction pour mapper le niveau à Collège ou Lycée
import { paysCourant } from './pays'
const getNiveauLabel = (niveau) => {
  if (!niveau) return ''
  const cleanNiveau = niveau.toString().trim().toLowerCase()
  
  if (
    /^(6|5|4|3)/.test(cleanNiveau) || 
    cleanNiveau.includes('coll') || 
    cleanNiveau.includes('clg')
  ) {
    return 'Collège'
  }
  
  if (
    /^(2|1|t)/.test(cleanNiveau) ||
    cleanNiveau.includes('seconde') || 
    cleanNiveau.includes('première') || 
    cleanNiveau.includes('premiere') || 
    cleanNiveau.includes('terminale') ||
    cleanNiveau.includes('lyc')
  ) {
    return 'Lycée'
  }
  
  return niveau.charAt(0).toUpperCase() + niveau.slice(1)
}

// Fonction pour grouper les matières par domaine
const groupMatieresByDomaine = (matieres) => {
  const domaines = {
    'Scientifiques': [],
    'Littéraires': [],
    'Langues': [],
    'EPS': [],
    'Autres': []
  }

  const scientifiques = ['Mathématiques', 'Physique-Chimie', 'SVT', 'Maths', 'Physique', 'Chimie', 'Sciences']
  const litteraires = ['Français', 'Histoire-Géographie', 'Philosophie', 'Histoire', 'Géographie', 'Philo', 'Littérature']
  const langues = ['Anglais', 'Espagnol', 'Allemand', 'Italien', 'Chinois', 'Arabe']
  const eps = ['EPS', 'Éducation Physique', 'Sport']

  matieres.forEach(matiere => {
    const nom = matiere.matiere.nom
    if (scientifiques.some(s => nom.includes(s))) {
      domaines['Scientifiques'].push(matiere)
    } else if (litteraires.some(l => nom.includes(l))) {
      domaines['Littéraires'].push(matiere)
    } else if (langues.some(l => nom.includes(l))) {
      domaines['Langues'].push(matiere)
    } else if (eps.some(e => nom.includes(e))) {
      domaines['EPS'].push(matiere)
    } else {
      domaines['Autres'].push(matiere)
    }
  })

  // Retourner seulement les domaines qui ont des matières
  return Object.entries(domaines)
    .filter(([, matieres]) => matieres.length > 0)
    .map(([nom, matieres]) => ({ nom, matieres }))
}

export async function downloadTablePdf({ title, subtitle, columns, rows, filename, landscape = false }) {
  const jsPDF = await chargerJsPDF()
  const autoTable = await chargerAutoTable()

  const doc = new jsPDF({ orientation: landscape ? 'landscape' : 'portrait' })
  const largeur = doc.internal.pageSize.getWidth()

  // Bandeau d'en-tête aux couleurs de l'école, titre en blanc.
  const couleur = couleurEcole()
  doc.setFillColor(...couleur)
  doc.rect(0, 0, largeur, 26, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFontSize(16)
  doc.text(title, 14, 14)
  if (subtitle) {
    doc.setFontSize(9)
    doc.setTextColor(226, 232, 240)
    doc.text(subtitle, 14, 21)
  }
  // Nom de l'établissement à droite du bandeau (le titre de l'onglet porte le nom de l'école connectée).
  const nomEcole = (typeof document !== 'undefined' && document.title) || ''
  if (nomEcole) {
    doc.setFontSize(10)
    doc.setTextColor(255, 255, 255)
    doc.text(nomEcole, largeur - 14, 14, { align: 'right' })
  }

  autoTable(doc, {
    head: [columns],
    body: rows,
    startY: 32,
    margin: { top: 32, bottom: 18, left: 14, right: 14 },
    styles: { fontSize: 9, cellPadding: 3, textColor: [21, 48, 42], lineColor: [228, 222, 211], lineWidth: 0.1 },
    headStyles: { fillColor: couleur, textColor: 255, fontStyle: "bold", halign: "center" },
    alternateRowStyles: { fillColor: [243, 240, 233] },
    didDrawPage: () => {
      // Pied de page : date de génération et numérotation.
      const hauteur = doc.internal.pageSize.getHeight()
      const page = doc.internal.getNumberOfPages()
      doc.setFontSize(8)
      doc.setTextColor(120, 113, 100)
      doc.text(`Généré le ${new Date().toLocaleDateString('fr-FR')}`, 14, hauteur - 8)
      doc.text(`Page ${page}`, largeur - 14, hauteur - 8, { align: 'right' })
    },
  })

  doc.save(filename)
}

export async function downloadElementPdf(element, filename) {
  const { default: html2canvas } = await import('html2canvas')
  const jsPDF = await chargerJsPDF()

  const canvas = await html2canvas(element, {
    scale: 2,
    backgroundColor: '#ffffff',
    useCORS: true,
    onclone: (clonedDoc) => {
      clonedDoc.querySelectorAll('.no-print').forEach((el) => el.remove())
    },
  })

  const imgData = canvas.toDataURL('image/png')

  const pdf = new jsPDF('p', 'mm', 'a4')
  const pageWidth = pdf.internal.pageSize.getWidth()
  const pageHeight = pdf.internal.pageSize.getHeight()
  const margin = 10

  // Fit the image inside the A4 page, preserving the aspect ratio
  const maxWidth = pageWidth - margin * 2
  const maxHeight = pageHeight - margin * 2

  let width = maxWidth
  let height = (canvas.height * width) / canvas.width

  if (height > maxHeight) {
    height = maxHeight
    width = (canvas.width * height) / canvas.height
  }

  const x = (pageWidth - width) / 2
  const y = (pageHeight - height) / 2

  pdf.addImage(imgData, 'PNG', x, y, width, height)
  pdf.save(filename)
}

export async function downloadBulletinsPdf({ bulletins, etablissement, trimestreLabel, filename }) {
  const jsPDF = await chargerJsPDF()
  const autoTable = await chargerAutoTable()

  const doc = new jsPDF('p', 'mm', 'a4')
  const pageWidth = doc.internal.pageSize.getWidth()
  const center = () => pageWidth / 2

  bulletins.forEach((bulletin, index) => {
    if (index > 0) doc.addPage()

    // En-tête
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(11)
    doc.setTextColor(15, 23, 42)
    doc.text(paysCourant().republique, center(), 12, { align: 'center' })

    doc.setFont('helvetica', 'italic')
    doc.setFontSize(9)
    doc.setTextColor(59, 130, 246)
    doc.text(etablissement.devise || paysCourant().devise, center(), 17, { align: 'center' })

    doc.setFont('helvetica', 'normal')
    doc.setTextColor(100, 116, 139)
    doc.text(etablissement.ministere || '', center(), 21, { align: 'center' })

    doc.setFont('helvetica', 'bold')
    doc.setFontSize(12)
    doc.setTextColor(15, 23, 42)
    doc.text(etablissement.nom || '', center(), 26, { align: 'center' })

    doc.setFontSize(11)
    doc.text(`BULLETIN DE NOTES — ${trimestreLabel}`, center(), 33, { align: 'center' })

    // Infos élève
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9)
    doc.setTextColor(15, 23, 42)
    const niveauLabel = getNiveauLabel(bulletin.classe?.niveau)
    doc.text(
      `Élève : ${bulletin.etudiant.prenom} ${bulletin.etudiant.nom}    Matricule : ${bulletin.etudiant.matricule || '—'}    Classe : ${bulletin.classe?.nom || '—'}    Niveau : ${niveauLabel || '—'}`,
      14,
      40
    )

    // Group subjects by domain
    const domaines = groupMatieresByDomaine(bulletin.matieres)
    let currentY = 40

    domaines.forEach((domaine) => {
      // Print domain name
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(9)
      doc.setTextColor(75, 85, 99)
      doc.text(domaine.nom, 14, currentY + 6)

      const body = domaine.matieres.map((row) => [
        row.matiere.nom,
        String(row.matiere.coefficient),
        Number(row.moyenne).toFixed(2),
        row.moyenne_classe != null ? Number(row.moyenne_classe).toFixed(2) : '—',
        row.appreciation || '',
      ])

      autoTable(doc, {
        head: [['Matière', 'Coef.', 'Moyenne', 'Moy. classe', 'Appréciation']],
        body,
        startY: currentY + 8,
        margin: { left: 14, right: 14 },
        styles: { fontSize: 8, cellPadding: 2.5 },
        headStyles: { fillColor: couleurEcole(), textColor: 255, fontSize: 8 },
        alternateRowStyles: { fillColor: [248, 250, 252] },
      })

      currentY = doc.lastAutoTable.finalY
    })

    // Appréciation + summary + signature
    const finalY = currentY

    // Draw a line separator before the summary
    doc.setDrawColor(226, 232, 240)
    doc.line(14, finalY + 4, pageWidth - 14, finalY + 4)

    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9)
    doc.setTextColor(15, 23, 42)
    doc.text(
      `Moyenne : ${bulletin.moyenne_generale ?? '—'}    Rang : ${bulletin.rang ? `${bulletin.rang}e/${bulletin.effectif}` : '—'}    Mention : ${bulletin.mention?.label || '—'}`,
      14,
      finalY + 10
    )

    doc.text(
      `Absences : ${bulletin.nb_absences ?? 0}    Retards : ${bulletin.nb_retards ?? 0}    Convocations : ${bulletin.nb_convocations ?? 0}`,
      14,
      finalY + 16
    )

    doc.text(`Appréciation générale : ${bulletin.appreciation_generale || '—'}`, 14, finalY + 24)

    doc.text(`Fait le ${new Date().toLocaleDateString('fr-FR')}`, 14, finalY + 34)
    doc.text(
      etablissement.responsable ? `Le Chef d'établissement, ${etablissement.responsable}` : "Le Chef d'établissement",
      pageWidth - 14,
      finalY + 34,
      { align: 'right' }
    )
  })

  doc.save(filename)
}


// jsPDF 4 et jspdf-autotable 5 exposent des exports nommés ; les versions précédentes, un export par défaut.
async function chargerJsPDF() {
  const module = await import('jspdf')
  return module.jsPDF || module.default
}

async function chargerAutoTable() {
  const module = await import('jspdf-autotable')
  return module.autoTable || module.default
}

// Couleur de l'école (variable --primary du thème actif), en RVB pour jsPDF. Vert par défaut.
function couleurEcole() {
  try {
    const valeur = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim()
    const hex = /^#([0-9a-f]{6})$/i.exec(valeur)
    if (hex) {
      const n = parseInt(hex[1], 16)
      return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
    }
  } catch {
    // Hors navigateur : couleur par défaut.
  }
  return [31, 92, 74]
}

// Reçu de paiement au format PDF, aux couleurs et au nom de l'école.
export async function downloadRecuPdf({ paiement, frais, eleve, etablissement, filename }) {
  const jsPDF = await chargerJsPDF()
  const doc = new jsPDF('p', 'mm', 'a4')
  const largeur = doc.internal.pageSize.getWidth()
  const [r, g, b] = couleurEcole()
  const monnaieEcole = etablissement.monnaie || 'FCFA'
  const somme = (v) => `${Math.round(Number(v) || 0).toLocaleString('fr-FR')} ${monnaieEcole}`
  const modes = { especes: 'Espèces', cheque: 'Chèque' }

  // Bandeau aux couleurs de l'école
  doc.setFillColor(r, g, b)
  doc.rect(0, 0, largeur, 42, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(16)
  doc.text(etablissement.nom || '', 14, 16)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  const coordonnees = [etablissement.adresse, etablissement.telephone ? `Tél : ${etablissement.telephone}` : null].filter(Boolean).join(' — ')
  if (coordonnees) doc.text(coordonnees, 14, 23)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(10)
  doc.text('REÇU DE PAIEMENT', largeur - 14, 16, { align: 'right' })
  doc.setFontSize(13)
  doc.text(`REC-${String(paiement.id).padStart(6, '0')}`, largeur - 14, 24, { align: 'right' })
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  doc.text(`Date : ${paiement.date_paiement}`, largeur - 14, 31, { align: 'right' })

  // Élève et frais
  doc.setTextColor(31, 42, 40)
  doc.setFontSize(10)
  let y = 56
  const ligne = (libelle, valeur, gras = false) => {
    doc.setFont('helvetica', 'normal')
    doc.setTextColor(107, 122, 118)
    doc.text(libelle, 14, y)
    doc.setTextColor(31, 42, 40)
    doc.setFont('helvetica', gras ? 'bold' : 'normal')
    doc.text(String(valeur ?? '—'), 70, y)
    y += 8
  }
  ligne('Élève', `${eleve.prenom} ${eleve.nom}`, true)
  ligne('Matricule', eleve.matricule)
  ligne('Classe', eleve.classe)
  ligne('Frais', `${frais.libelle} (${frais.annee_scolaire})`)
  ligne('Mode de paiement', modes[paiement.mode_paiement] || paiement.mode_paiement)
  if (paiement.reference) ligne('Référence', paiement.reference)

  // Montant encaissé
  y += 4
  doc.setFillColor(r, g, b)
  doc.setDrawColor(r, g, b)
  doc.roundedRect(14, y, largeur - 28, 20, 3, 3, 'S')
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(12)
  doc.setTextColor(r, g, b)
  doc.text('Montant encaissé', 20, y + 13)
  doc.setFontSize(16)
  doc.text(somme(paiement.montant), largeur - 20, y + 13, { align: 'right' })
  y += 32

  // Situation après ce paiement
  doc.setTextColor(31, 42, 40)
  doc.setFontSize(10)
  ligne('Total des frais', somme(frais.montant_total))
  ligne('Total encaissé', somme(frais.montant_paye))
  ligne('Reste à payer', somme(frais.reste), true)
  if (Number(frais.reste) <= 0) {
    doc.setFont('helvetica', 'bold')
    doc.setTextColor(22, 163, 74)
    doc.setFontSize(14)
    doc.text('SOLDÉ', 14, y + 4)
  }

  // Signatures
  y = 230
  doc.setDrawColor(180, 180, 180)
  doc.setTextColor(107, 122, 118)
  doc.setFontSize(9)
  doc.line(14, y, 90, y)
  doc.text('Signature du caissier', 14, y + 5)
  doc.line(largeur - 90, y, largeur - 14, y)
  doc.text('Cachet de l’établissement', largeur - 90, y + 5)

  doc.setFontSize(8)
  doc.text(`Édité le ${new Date().toLocaleString('fr-FR')} — ${etablissement.nom || ''}`, largeur / 2, 285, { align: 'center' })

  doc.save(filename)
}
