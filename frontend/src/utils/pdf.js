// Fonction pour mapper le niveau à Collège ou Lycée
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
  const { default: jsPDF } = await import('jspdf')
  const { default: autoTable } = await import('jspdf-autotable')

  const doc = new jsPDF({ orientation: landscape ? 'landscape' : 'portrait' })

  doc.setFontSize(16)
  doc.setTextColor(15, 23, 42)
  doc.text(title, 14, 16)

  if (subtitle) {
    doc.setFontSize(10)
    doc.setTextColor(100, 116, 139)
    doc.text(subtitle, 14, 23)
  }

  autoTable(doc, {
    head: [columns],
    body: rows,
    startY: subtitle ? 28 : 24,
    styles: { fontSize: 9, cellPadding: 3 },
    headStyles: { fillColor: [79, 70, 229], textColor: 255, fontSize: 9 },
    alternateRowStyles: { fillColor: [248, 250, 252] },
  })

  doc.save(filename)
}

export async function downloadElementPdf(element, filename) {
  const { default: html2canvas } = await import('html2canvas')
  const { default: jsPDF } = await import('jspdf')

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
  const { default: jsPDF } = await import('jspdf')
  const { default: autoTable } = await import('jspdf-autotable')

  const doc = new jsPDF('p', 'mm', 'a4')
  const pageWidth = doc.internal.pageSize.getWidth()
  const center = () => pageWidth / 2

  bulletins.forEach((bulletin, index) => {
    if (index > 0) doc.addPage()

    // En-tête
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(11)
    doc.setTextColor(15, 23, 42)
    doc.text('RÉPUBLIQUE DU TCHAD', center(), 12, { align: 'center' })

    doc.setFont('helvetica', 'italic')
    doc.setFontSize(9)
    doc.setTextColor(59, 130, 246)
    doc.text(etablissement.devise || 'Unité — Travail — Progrès', center(), 17, { align: 'center' })

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
        headStyles: { fillColor: [79, 70, 229], textColor: 255, fontSize: 8 },
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

