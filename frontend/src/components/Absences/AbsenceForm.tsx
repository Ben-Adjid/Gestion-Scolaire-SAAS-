import React, { useState } from 'react';
import { useApi } from '../../hooks/useApi';

interface AbsenceFormProps {
  studentId: number;
  onSuccess?: () => void;
}

export const AbsenceForm: React.FC<AbsenceFormProps> = ({ studentId, onSuccess }) => {
  const { post } = useApi();
  const [formData, setFormData] = useState({
    date: new Date().toISOString().split('T')[0],
    heure_debut: '08:00',
    heure_fin: '10:00',
    justifiee: false,
    motif: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setLoading(true);
      setError(null);

      await post('/absences', {
        etudiant_id: studentId,
        ...formData,
      });

      setFormData({
        date: new Date().toISOString().split('T')[0],
        heure_debut: '08:00',
        heure_fin: '10:00',
        justifiee: false,
        motif: '',
      });
      onSuccess?.();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to record absence');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="absence-form">
      <h3>Enregistrer une Absence</h3>
      
      {error && <div className="error-message">{error}</div>}

      <div className="form-group">
        <label htmlFor="date">Date</label>
        <input
          id="date"
          type="date"
          value={formData.date}
          onChange={(e) => setFormData({ ...formData, date: e.target.value })}
          required
        />
      </div>

      <div className="form-row">
        <div className="form-group">
          <label htmlFor="heure_debut">Heure Début</label>
          <input
            id="heure_debut"
            type="time"
            value={formData.heure_debut}
            onChange={(e) => setFormData({ ...formData, heure_debut: e.target.value })}
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="heure_fin">Heure Fin</label>
          <input
            id="heure_fin"
            type="time"
            value={formData.heure_fin}
            onChange={(e) => setFormData({ ...formData, heure_fin: e.target.value })}
            required
          />
        </div>
      </div>

      <div className="form-group checkbox">
        <input
          id="justifiee"
          type="checkbox"
          checked={formData.justifiee}
          onChange={(e) => setFormData({ ...formData, justifiee: e.target.checked })}
        />
        <label htmlFor="justifiee">Justifiée</label>
      </div>

      <div className="form-group">
        <label htmlFor="motif">Motif</label>
        <textarea
          id="motif"
          value={formData.motif}
          onChange={(e) => setFormData({ ...formData, motif: e.target.value })}
          maxLength={500}
          rows={3}
        />
      </div>

      <button type="submit" disabled={loading}>
        {loading ? 'Enregistrement...' : 'Enregistrer'}
      </button>
    </form>
  );
};
