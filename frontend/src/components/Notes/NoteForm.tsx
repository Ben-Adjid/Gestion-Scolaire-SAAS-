import React, { useState } from 'react';
import { useApi } from '../../hooks/useApi';

interface NoteFormProps {
  studentId: number;
  onSuccess?: () => void;
}

export const NoteForm: React.FC<NoteFormProps> = ({ studentId, onSuccess }) => {
  const { post } = useApi();
  const [formData, setFormData] = useState({
    matiere_id: '',
    note: '',
    trimestre: '1',
    appreciation: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      setLoading(true);
      setError(null);
      
      await post('/notes', {
        etudiant_id: studentId,
        ...formData,
        note: parseFloat(formData.note),
      });

      setFormData({
        matiere_id: '',
        note: '',
        trimestre: '1',
        appreciation: '',
      });
      onSuccess?.();
    } catch (err: any) {
      setError(err.response?.data?.message || 'Failed to save note');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="note-form">
      <h3>Saisir une Note</h3>
      
      {error && <div className="error-message">{error}</div>}

      <div className="form-group">
        <label htmlFor="matiere_id">Matière</label>
        <select
          id="matiere_id"
          value={formData.matiere_id}
          onChange={(e) => setFormData({ ...formData, matiere_id: e.target.value })}
          required
        >
          <option value="">Sélectionner une matière</option>
          <option value="1">Mathématiques</option>
          <option value="2">Français</option>
          <option value="3">Anglais</option>
          <option value="4">Sciences</option>
        </select>
      </div>

      <div className="form-group">
        <label htmlFor="note">Note (0-20)</label>
        <input
          id="note"
          type="number"
          min="0"
          max="20"
          step="0.5"
          value={formData.note}
          onChange={(e) => setFormData({ ...formData, note: e.target.value })}
          required
        />
      </div>

      <div className="form-group">
        <label htmlFor="trimestre">Trimestre</label>
        <select
          id="trimestre"
          value={formData.trimestre}
          onChange={(e) => setFormData({ ...formData, trimestre: e.target.value })}
        >
          <option value="1">T1</option>
          <option value="2">T2</option>
          <option value="3">T3</option>
        </select>
      </div>

      <div className="form-group">
        <label htmlFor="appreciation">Appréciation</label>
        <textarea
          id="appreciation"
          value={formData.appreciation}
          onChange={(e) => setFormData({ ...formData, appreciation: e.target.value })}
          maxLength={500}
          rows={3}
        />
      </div>

      <button type="submit" disabled={loading}>
        {loading ? 'Sauvegarde...' : 'Enregistrer'}
      </button>
    </form>
  );
};
